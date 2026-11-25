<?php

namespace App\Payroll;

use App\Business;
use App\BusinessLocation;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: the module switch, the settings (paxhitestbed Payroll Settings), and the
 * Cameroon payroll taxes every business starts from, set up by themselves the first time payroll is
 * used (paxhitestbed CameroonTaxTable: the DGI barème rendered as five income-tax bands, verified
 * there against 11,972 printed rows; the CNPS on the pay capped at 750,000 a month, with the
 * family-allowance and work-accident rates of the type of business).
 */
class PayrollRules
{
    const DEFAULTS = [
        'require_approval' => 0,
        'carry_over_lines' => 1,
        'prorate_partial_months' => 0,
        'proration_basis' => 'calendar_days',   // calendar_days | working_days
        'location_id' => null,
        'sector' => null,                          // the type of business (SECTORS); guessed until set
        'risk_group' => null,                      // CNPS work-accident group A / B / C; the sector's until set
        'advance_account_code' => '421',          // staff advances: loans given, repayments withheld
        // the file a bank takes to pay salaries (paxhitestbed bank file)
        'bank_file_columns' => ['name', 'bank_name', 'account_number', 'amount', 'reference'],
        'bank_file_delimiter' => ',',              // , ; tab
        'bank_file_header' => 1,
        'bank_file_reference' => 'SALARY {MONTH} {YEAR}',
        'bank_file_decimals' => 0,
    ];

    /** The columns a bank file can have, in the order they are written. */
    const BANK_COLUMNS = ['staff_no', 'name', 'bank_name', 'account_number', 'amount', 'currency', 'reference', 'month', 'cnps_no', 'job_title'];

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
            if (empty($s['sector']) || ! isset(self::SECTORS[$s['sector']])) {
                $s['sector'] = self::guessSector($business_id);
            }
            if (empty($s['risk_group']) || ! isset(self::RISK_RATES[$s['risk_group']])) {
                $s['risk_group'] = self::SECTORS[$s['sector']]['risk'];
            }
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
     * The types of business, for what the CNPS charges the employer: the family-allowance rate
     * (7% in general, 5.65% in agriculture, 3.7% in private education) and the work-accident risk
     * group (A low 1.75%, B medium 2.5%, C high 5%). The admin can change both in Payroll Settings.
     */
    const SECTORS = [
        'commerce' => ['family' => 7.0, 'risk' => 'A'],      // shop, pharmacy, offices, services
        'health' => ['family' => 7.0, 'risk' => 'A'],        // clinic, hospital
        'hotel' => ['family' => 7.0, 'risk' => 'A'],         // hotel, restaurant
        'school' => ['family' => 3.7, 'risk' => 'A'],        // private education
        'agriculture' => ['family' => 5.65, 'risk' => 'B'],  // farm, plantation
        'industry' => ['family' => 7.0, 'risk' => 'C'],      // construction, forestry, factory, mining
    ];

    const RISK_RATES = ['A' => 1.75, 'B' => 2.5, 'C' => 5.0];

    /** The monthly pay the CNPS contributions are worked out on is capped here. */
    const CNPS_CEILING = 750000;

    /** The type of business, guessed from its modules and name until the admin says otherwise. */
    public static function guessSector($business_id)
    {
        $b = Business::find($business_id);
        $mods = (array) optional($b)->enabled_modules;
        $name = mb_strtolower((string) optional($b)->name);
        if (preg_match('/farm|agri|ferme|plantation|élevage|elevage/u', $name)) {
            return 'agriculture';
        }
        if (preg_match('/school|college|collège|ecole|école|lycée|lycee|institut|academy|académie/u', $name)) {
            return 'school';
        }
        if (in_array('hospital', $mods, true) || preg_match('/clinic|clinique|hospital|hôpital|hopital|santé|sante|medical|médical/u', $name)) {
            return 'health';
        }
        if (in_array('hotel', $mods, true) || preg_match('/hotel|hôtel|motel|auberge|restaurant/u', $name)) {
            return 'hotel';
        }

        return 'commerce';
    }

    /** The taxes are there before anyone asks: the first time payroll is used, the defaults are set up. */
    public static function ensureDefaults($business_id)
    {
        if (! self::hasTaxes($business_id)) {
            self::installCameroonTaxes($business_id);
        }
        if (! PayrollLineType::where('business_id', $business_id)->exists()) {
            self::installLineTypes($business_id);
        }
    }

    /**
     * The Cameroon payroll taxes (paxhitestbed TaxSettingSeeder + CameroonTaxTable), for this
     * business's type:
     *  - IRPP in five bands reproducing the DGI barème, CAC 10% of it;
     *  - CNPS old-age pension 4.2% each side, family allowances (employer) and work accident
     *    (employer), all on the pay capped at 750,000 a month;
     *  - FNE 1% employer, CFC 1% staff / 1.5% employer, audio-visual (RAV) and local development (TDL)
     *    flat amounts by band.
     */
    public static function installCameroonTaxes($business_id)
    {
        if (self::hasTaxes($business_id)) {
            return false;
        }
        $s = self::settings($business_id);

        DB::transaction(function () use ($business_id, $s) {
            $from = null;   // in force with no start date, so earlier months can still be paid (catching up)
            $group = fn ($code, $key, array $extra = []) => PayrollTaxGroup::create(array_merge([
                'business_id' => $business_id, 'code' => $code, 'title' => __('payroll.tax_'.$key), 'status' => 1, 'effective_from' => $from, 'liability_code' => '447',
            ], $extra));
            $band = fn (array $v) => PayrollTaxSetting::create(array_merge(['business_id' => $business_id, 'status' => 1, 'effective_from' => $from, 'paid_by' => 'employee', 'tax_type' => 1, 'liability_code' => '447'], $v));
            // a CNPS contribution: the rate up to the ceiling, the same amount as a flat sum above it
            $capped = function ($code, $key, $paidBy, $staffPct, $employerPct, $order) use ($band) {
                $cap = self::CNPS_CEILING;
                $band(['code' => $code, 'title' => __('payroll.tax_'.$key), 'paid_by' => $paidBy, 'min_amount' => 0, 'max_amount' => $cap,
                    'percentage' => $staffPct, 'employer_percentage' => $employerPct, 'liability_code' => '431', 'bracket_order' => $order]);
                $band(['code' => $code.'_cap', 'title' => __('payroll.tax_'.$key).' '.__('payroll.at_ceiling'), 'paid_by' => $paidBy, 'tax_type' => 2,
                    'min_amount' => $cap + 0.01, 'max_amount' => self::OPEN_TOP, 'fixed_amount' => round($cap * $staffPct / 100, 2),
                    'employer_fixed_amount' => round($cap * $employerPct / 100, 2), 'liability_code' => '431', 'bracket_order' => $order + 1]);
            };

            $pit = $group('PIT', 'pit', ['is_income_tax' => 1, 'is_progressive' => 1, 'display_order' => 1]);
            foreach ([[1, 0, 316616, 6.58, 63323.20, '10% x 0.658'], [2, 316617, 443262, 9.87, 147754.14, '15% x 0.658'], [3, 443263, 696555, 16.45, 265957.45, '25% x 0.658'],
                [4, 696556, 1785714, 23.03, 388985.38, '35% x 0.658'], [5, 1785715, self::OPEN_TOP, 24.50, 472789.12, '35% x 0.70']] as [$o, $min, $max, $pct, $thr, $note]) {
                $band(['tax_group_id' => $pit->id, 'title' => __('payroll.tax_pit').' '.$o, 'bracket_order' => $o, 'min_amount' => $min, 'max_amount' => $max,
                    'percentage' => $pct, 'threshold' => $thr, 'note' => $note]);
            }
            $band(['code' => 'cm_cac', 'title' => __('payroll.tax_cac'), 'is_dependent' => 1, 'depends_on_type' => 'tax_group', 'depends_on_id' => $pit->id, 'percentage' => 10, 'bracket_order' => 10]);
            $sector = self::SECTORS[$s['sector']] ?? self::SECTORS['commerce'];
            $capped('cm_pension', 'cnps', 'both', 4.2, 4.2, 20);
            $capped('cm_family', 'family', 'employer', 0, $sector['family'], 22);
            $capped('cm_accident', 'accident', 'employer', 0, self::RISK_RATES[$s['risk_group']] ?? 1.75, 24);
            $band(['code' => 'cm_fne', 'title' => __('payroll.tax_fne'), 'paid_by' => 'employer', 'min_amount' => 0, 'max_amount' => self::OPEN_TOP, 'employer_percentage' => 1, 'liability_code' => '431', 'bracket_order' => 30]);

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
        });

        return true;
    }

    /** The usual allowance and deduction types. */
    public static function installLineTypes($business_id)
    {
        foreach ([['allowance', 'type_transport', 0], ['allowance', 'type_housing', 1], ['allowance', 'type_bonus', 1], ['allowance', 'type_overtime', 1],
            ['deduction', 'type_absence', 0], ['deduction', 'type_penalty', 0]] as [$kind, $key, $taxed]) {
            PayrollLineType::create(['business_id' => $business_id, 'kind' => $kind, 'title' => __('payroll.'.$key), 'is_taxable' => $taxed, 'status' => 1]);
        }
    }

    /**
     * Back to the defaults: every tax of the business is replaced by the Cameroon set for its type.
     * Payrolls already paid keep their figures (their tax lines carry their own amounts); a payroll
     * generated but not paid is worked out again when it is next opened and saved.
     */
    public static function restoreDefaults($business_id)
    {
        DB::transaction(function () use ($business_id) {
            PayrollTaxSetting::where('business_id', $business_id)->delete();
            PayrollTaxGroup::where('business_id', $business_id)->delete();
            self::installCameroonTaxes($business_id);
        });
    }

    /** The type of business or the risk group changed: set the family-allowance and work-accident rates again. */
    public static function applySector($business_id)
    {
        $s = self::settings($business_id);
        $rates = ['cm_family' => (self::SECTORS[$s['sector']] ?? self::SECTORS['commerce'])['family'], 'cm_accident' => self::RISK_RATES[$s['risk_group']] ?? 1.75];
        foreach ($rates as $code => $pct) {
            PayrollTaxSetting::where('business_id', $business_id)->where('code', $code)->update(['employer_percentage' => $pct]);
            PayrollTaxSetting::where('business_id', $business_id)->where('code', $code.'_cap')->update(['employer_fixed_amount' => round(self::CNPS_CEILING * $pct / 100, 2)]);
        }
    }
}
