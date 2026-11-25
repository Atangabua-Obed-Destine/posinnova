<?php

namespace App\Payroll;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova payroll: a group of bands (paxhitestbed TaxGroup): income tax, local development tax,
 * housing fund, audio-visual tax. A group charges ONE band: the active band with the highest
 * minimum at or below the pay (step lookup, never summed across bands).
 */
class PayrollTaxGroup extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['status' => 'boolean', 'is_income_tax' => 'boolean', 'is_progressive' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date'];

    public function bands()
    {
        return $this->hasMany(PayrollTaxSetting::class, 'tax_group_id')->orderBy('bracket_order')->orderBy('min_amount');
    }

    public static function inForce($business_id, $date)
    {
        $d = Carbon::parse($date)->toDateString();

        return static::where('business_id', $business_id)->where('status', 1)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $d))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d))
            ->orderBy('display_order')->orderBy('id')->get();
    }

    public function bandsInForce($date)
    {
        $d = Carbon::parse($date)->toDateString();

        return $this->bands()->where('status', 1)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $d))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d))->get();
    }

    /** The band a pay falls in (highest minimum at or below it), or null. */
    public function bandFor(float $pay, $date)
    {
        $band = null;
        foreach ($this->bandsInForce($date) as $b) {
            if ($pay >= (float) $b->min_amount && ($band === null || (float) $b->min_amount >= (float) $band->min_amount)) {
                $band = $b;
            }
        }

        return $band;
    }

    /** Pays the bands do not cover (between two bands, or above the last): [[from, to|null], …]. */
    public function gaps($date)
    {
        $bands = $this->bandsInForce($date)->sortBy('min_amount')->values();
        $out = [];
        for ($i = 0; $i < $bands->count(); $i++) {
            $max = (float) $bands[$i]->max_amount;
            $next = $bands[$i + 1] ?? null;
            if ($next && (float) $next->min_amount > $max + 1) {
                $out[] = [$max + 1, (float) $next->min_amount - 1];
            }
        }

        return $out;
    }
}
