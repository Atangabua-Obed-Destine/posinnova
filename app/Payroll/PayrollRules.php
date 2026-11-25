<?php

namespace App\Payroll;

use App\Business;
use App\BusinessLocation;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: the module switch, the settings (paxhitestbed Payroll Settings), and the
 * Cameroon payroll taxes a new business starts from (paxhitestbed CameroonTaxTable: the DGI
 * barème rendered as five income-tax bands, verified there against 11,972 printed rows).
 */
class PayrollRules
{
    const DEFAULTS = [
        'require_approval' => 0,
        'carry_over_lines' => 1,
        'prorate_partial_months' => 0,
        'proration_basis' => 'calendar_days',   // calendar_days | working_days
        'location_id' => null,
    ];

    const OPEN_TOP = 50000000;

    private static $cache = [];

    public static function enabled($business_id = null)
    {
        $business_id = $business_id ?: session('business.id');
        $mods = session('business.id') == $business_id ? (array) session('business.enabled_modules') : (array) optional(Business::find($business_id))->enabled_modules;

        return in_array('payroll', $mods ?: [], true);
    }

    public static function settings($business_id)
    {
        if (! isset(self::$cache[$business_id])) {
            $raw = DB::table('payroll_settings')->where('business_id', $business_id)->value('settings');
            $s = array_merge(self::DEFAULTS, (array) json_decode((string) $raw, true));
            if (empty($s['location_id'])) {
                $s['location_id'] = BusinessLocation::where('business_id', $business_id)->orderBy('id')->value('id');
            }
            self::$cache[$business_id] = $s;
        }

        return self::$cache[$business_id];
    }

    public static function saveSettings($business_id, array $values)
    {
        $s = array_merge(self::settings($business_id), array_intersect_key($values, self::DEFAULTS));
        DB::table('payroll_settings')->updateOrInsert(['business_id' => $business_id], ['settings' => json_encode($s), 'updated_at' => now(), 'created_at' => now()]);
        unset(self::$cache[$business_id]);

        return self::settings($business_id);
    }

    public static function forget()
    {
        self::$cache = [];
    }

    /** Has this business any payroll tax set up? */
    public static function hasTaxes($business_id)
    {
        return PayrollTaxGroup::where('business_id', $business_id)->exists() || PayrollTaxSetting::where('business_id', $business_id)->exists();
    }

    /**
     * The Cameroon payroll taxes (paxhitestbed TaxSettingSeeder + CameroonTaxTable), made once:
     * IRPP in five bands, CAC 10% of it, CNPS 4.2% each side capped at 75,000, CFC 1% / 1.5%,
     * FNE 1% employer, audio-visual (CRTV) and local development (TDL) flat amounts, and the
     * industrial-accident group left off with no band (each business's sector rate).
     */
    public static function installCameroonTaxes($business_id)
    {
        if (self::hasTaxes($business_id)) {
            return false;
        }

        return DB::transaction(function () use ($business_id) {
            // in force with no start date, so earlier months can still be paid (catching up)
            $from = null;
            $group = fn ($code, $key, array $extra = []) => PayrollTaxGroup::create(array_merge([
                'business_id' => $business_id, 'code' => $code, 'title' => __('payroll.tax_'.$key), 'status' => 1, 'effective_from' => $from, 'liability_code' => '447',
            ], $extra));
            $band = fn (array $v) => PayrollTaxSetting::create(array_merge(['business_id' => $business_id, 'status' => 1, 'effective_from' => $from, 'paid_by' => 'employee', 'tax_type' => 1, 'liability_code' => '447'], $v));

            $pit = $group('PIT', 'pit', ['is_income_tax' => 1, 'is_progressive' => 1, 'display_order' => 1]);
            foreach ([[1, 0, 316616, 6.58, 63323.20, '10% x 0.658'], [2, 316617, 443262, 9.87, 147754.14, '15% x 0.658'], [3, 443263, 696555, 16.45, 265957.45, '25% x 0.658'],
                [4, 696556, 1785714, 23.03, 388985.38, '35% x 0.658'], [5, 1785715, self::OPEN_TOP, 24.50, 472789.12, '35% x 0.70, past the CNPS cap']] as [$o, $min, $max, $pct, $thr, $note]) {
                $band(['tax_group_id' => $pit->id, 'title' => __('payroll.tax_pit').' '.$o, 'bracket_order' => $o, 'min_amount' => $min, 'max_amount' => $max,
                    'percentage' => $pct, 'threshold' => $thr, 'note' => $note]);
            }
            $band(['title' => __('payroll.tax_cac'), 'is_dependent' => 1, 'depends_on_type' => 'tax_group', 'depends_on_id' => $pit->id, 'percentage' => 10, 'bracket_order' => 10]);
            $band(['title' => __('payroll.tax_cnps'), 'paid_by' => 'both', 'min_amount' => 0, 'max_amount' => 1785714, 'percentage' => 4.2, 'employer_percentage' => 4.2, 'liability_code' => '431', 'bracket_order' => 20]);
            $band(['title' => __('payroll.tax_cnps_cap'), 'paid_by' => 'both', 'tax_type' => 2, 'min_amount' => 1785715, 'max_amount' => self::OPEN_TOP, 'fixed_amount' => 75000, 'employer_fixed_amount' => 75000, 'liability_code' => '431', 'bracket_order' => 21]);
            $band(['title' => __('payroll.tax_fne'), 'paid_by' => 'employer', 'min_amount' => 0, 'max_amount' => self::OPEN_TOP, 'employer_percentage' => 1, 'liability_code' => '431', 'bracket_order' => 30]);

            $hlf = $group('HLF', 'hlf', ['display_order' => 3]);
            $band(['tax_group_id' => $hlf->id, 'title' => __('payroll.tax_hlf'), 'paid_by' => 'both', 'min_amount' => 52000, 'max_amount' => self::OPEN_TOP, 'percentage' => 1, 'employer_percentage' => 1.5, 'bracket_order' => 1]);

            $crtv = $group('CRTV', 'crtv', ['display_order' => 4]);
            foreach ([[52000, 100000, 750], [100250, 200000, 1950], [200250, 300000, 3250], [300250, 400000, 4550], [400250, 500000, 5850], [500250, 600000, 7150],
                [600250, 700000, 8450], [700250, 800000, 9750], [800250, 900000, 11050], [900250, 1000000, 12350], [1000250, self::OPEN_TOP, 13000]] as $i => [$min, $max, $amt]) {
                $band(['tax_group_id' => $crtv->id, 'title' => __('payroll.tax_crtv'), 'tax_type' => 2, 'bracket_order' => $i + 1, 'min_amount' => $min, 'max_amount' => $max, 'fixed_amount' => $amt]);
            }

            $ldt = $group('LDT', 'ldt', ['display_order' => 2]);
            foreach ([[0, 60000, 0], [62000, 75000, 250], [75001, 100000, 500], [100001, 125000, 750], [125001, 150000, 1000], [150001, 200000, 1250],
                [200001, 250000, 1500], [250001, 300000, 2000], [300001, 500000, 2250], [500001, self::OPEN_TOP, 2500]] as $i => [$min, $max, $amt]) {
                $band(['tax_group_id' => $ldt->id, 'title' => __('payroll.tax_ldt'), 'tax_type' => 2, 'bracket_order' => $i + 1, 'min_amount' => $min, 'max_amount' => $max, 'fixed_amount' => $amt]);
            }

            $group('IA', 'ia', ['status' => 0, 'display_order' => 5, 'liability_code' => '431']);

            // the usual allowance and deduction types
            foreach ([['allowance', 'type_transport', 0], ['allowance', 'type_housing', 1], ['allowance', 'type_bonus', 1], ['allowance', 'type_overtime', 1],
                ['deduction', 'type_absence', 0], ['deduction', 'type_penalty', 0]] as [$kind, $key, $taxed]) {
                PayrollLineType::create(['business_id' => $business_id, 'kind' => $kind, 'title' => __('payroll.'.$key), 'is_taxable' => $taxed, 'status' => 1]);
            }

            return true;
        });
    }
}
