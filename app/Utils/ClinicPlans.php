<?php

namespace App\Utils;

use App\BusinessLocation;
use App\ClinicSubscription;
use App\ClinicSubscriptionPeriod;
use App\Transaction;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: monthly plans.
 *
 * Billing periods (Clinic settings, "Monthly plans"):
 *   start  each patient is billed on the day of the month they started
 *          (started on the 31st: the last day of shorter months); every
 *          period is a full month at the full price.
 *   fixed  everyone is billed on the same day (1-28). The first period runs
 *          from the start date to the day before the next billing day and is
 *          charged pro rata for its days, rounded to a whole amount.
 *
 * A billing run bills every period whose start date has come, catching up
 * missed days (the server was off), never the same month twice (unique
 * subscription + period start). Each period is an invoice on the patient
 * account at the plan's price on the day it is billed.
 *
 * Coverage of a visit on a date:
 *   the period that contains the date must exist; if its invoice is not
 *   fully paid, it still covers during the grace days, then the unpaid rule
 *   applies (charge per visit as usual / block check-in / cover anyway);
 *   a period with a visit limit stops covering once the limit is used.
 */
class ClinicPlans
{
    /** Safety stop for catch-up billing of one subscription in one run. */
    const MAX_CATCH_UP = 24;

    /**
     * The first billing day strictly after $date.
     */
    public static function nextBoundary(ClinicSubscription $sub, Carbon $date, array $settings)
    {
        if (($settings['plan_billing_anchor'] ?? 'start') === 'fixed') {
            $day = max(1, min(28, (int) ($settings['plan_fixed_day'] ?? 1)));
            $candidate = $date->copy()->startOfDay()->day($day);

            return $candidate->gt($date) ? $candidate : $date->copy()->startOfDay()->startOfMonth()->addMonthNoOverflow()->day($day);
        }

        // anchored on the start date so the 31st does not drift to the 28th
        $start = $sub->start_date->copy()->startOfDay();
        $k = max(1, $start->diffInMonths($date));
        while ($start->copy()->addMonthsNoOverflow($k)->lte($date)) {
            $k++;
        }
        while ($k > 1 && $start->copy()->addMonthsNoOverflow($k - 1)->gt($date)) {
            $k--;
        }

        return $start->copy()->addMonthsNoOverflow($k);
    }

    /**
     * Dates and price of the period starting on $from.
     *
     * @return array [Carbon start, Carbon end, float amount]
     */
    public static function periodFrom(ClinicSubscription $sub, Carbon $from, array $settings, $price)
    {
        $from = $from->copy()->startOfDay();
        $next = self::nextBoundary($sub, $from, $settings);
        $end = $next->copy()->subDay();
        $amount = (float) $price;

        if (($settings['plan_billing_anchor'] ?? 'start') === 'fixed') {
            $day = max(1, min(28, (int) ($settings['plan_fixed_day'] ?? 1)));
            if ($from->day != $day) {
                $prev = $next->copy()->subMonthNoOverflow()->day($day);
                $full = max(1, $prev->diffInDays($next));
                $amount = round($price * $from->diffInDays($next) / $full);
            }
        }

        return [$from, $end, $amount];
    }

    /**
     * Bills every period of one subscription that has started by $today.
     *
     * @return ClinicSubscriptionPeriod[] the periods billed now
     */
    public static function billSubscription($subscription_id, $today = null, $user_id = null)
    {
        $today = ($today ? Carbon::parse($today) : Carbon::today())->startOfDay();
        $billed = [];

        DB::transaction(function () use ($subscription_id, $today, $user_id, &$billed) {
            $sub = ClinicSubscription::with('package')->lockForUpdate()->find($subscription_id);
            if (! $sub || $sub->status != 'active') {
                return;
            }
            $settings = ClinicSettings::all($sub->business_id);
            $package = $sub->package;
            $by = $user_id ?: $sub->created_by;

            for ($n = 0; $n < self::MAX_CATCH_UP && $sub->next_bill_date->lte($today); $n++) {
                [$start, $end, $amount] = self::periodFrom($sub, $sub->next_bill_date, $settings, (float) $package->price);

                $exists = ClinicSubscriptionPeriod::where('subscription_id', $sub->id)->whereDate('period_start', $start)->exists();
                if (! $exists) {
                    $billed[] = self::createPeriod($sub, $package, $start, $end, $amount, $by);
                }

                $sub->next_bill_date = $end->copy()->addDay();
                $sub->save();
            }
        });

        return $billed;
    }

    private static function createPeriod(ClinicSubscription $sub, $package, Carbon $start, Carbon $end, $amount, $user_id)
    {
        $util = new Util();
        $label = $util->format_date($start->toDateString()).' – '.$util->format_date($end->toDateString());
        $location_id = $sub->location_id ?: BusinessLocation::where('business_id', $sub->business_id)->value('id');

        $invoice = null;
        if ($amount > 0) {
            // the package's own service product; recreated only if it is gone
            $service = ! empty($package->variation_id) ? Variation::find($package->variation_id) : null;
            if (! $service) {
                $service = ClinicServices::forPackage($package, $user_id);
            }
            $invoice = (new \App\Utils\HospitalBillingUtil())->chargePatientFee([
                'no_insurance' => ! ClinicSettings::get($sub->business_id, 'insurance_covers_plans'),
                'business_id' => $sub->business_id,
                'contact_id' => $sub->contact_id,
                'lines' => [['variation_id' => $service->id, 'product_id' => $service->product_id,
                    'quantity' => 1, 'unit_price' => $amount, 'note' => $label]],
                'charge_type' => 'other',
                'description' => __('clinic.plan_invoice_description', ['plan' => $package->name, 'period' => $label]),
                'location_id' => $location_id,
                'created_by' => $user_id,
                'reference_type' => 'clinic_subscription',
            ]);
        }

        return ClinicSubscriptionPeriod::create([
            'business_id' => $sub->business_id,
            'subscription_id' => $sub->id,
            'contact_id' => $sub->contact_id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'amount' => $amount,
            'visits_allowed' => $package->visits_per_month ?: null,
            'transaction_id' => optional($invoice)->id,
        ]);
    }

    /**
     * Bills every active plan of a business that is due by $today.
     *
     * @return int number of periods billed
     */
    public static function billDue($business_id, $today = null, $user_id = null)
    {
        $today = ($today ? Carbon::parse($today) : Carbon::today())->startOfDay();
        $count = 0;
        $ids = ClinicSubscription::forBusiness($business_id)->active()
            ->whereDate('next_bill_date', '<=', $today)->pluck('id');
        foreach ($ids as $id) {
            try {
                $count += count(self::billSubscription($id, $today, $user_id));
            } catch (\Exception $e) {
                \Log::emergency('Clinic plan billing failed for subscription '.$id.': '.$e->getMessage());
            }
        }

        return $count;
    }

    public static function activeFor($business_id, $contact_id)
    {
        return ClinicSubscription::forBusiness($business_id)->active()->where('contact_id', $contact_id)->with('package')->first();
    }

    /**
     * Is the invoice of a period fully paid? (no invoice = nothing to pay)
     */
    public static function periodPaid(ClinicSubscriptionPeriod $period)
    {
        if (empty($period->transaction_id)) {
            return true;
        }

        return Transaction::where('id', $period->transaction_id)->value('payment_status') == 'paid';
    }

    /**
     * Whether a plan covers a visit by this patient on $date.
     *
     * @return array state: none | not_started | covered | grace | unpaid_cover |
     *               unpaid_charge | unpaid_block | limit_reached;
     *               covers (bool), blocks (bool), message, and the
     *               subscription / period / used / allowed / paid when known.
     */
    public static function coverage($business_id, $contact_id, $date = null)
    {
        $date = ($date ? Carbon::parse($date) : Carbon::today())->startOfDay();
        $out = ['state' => 'none', 'covers' => false, 'blocks' => false, 'message' => '',
            'subscription' => null, 'period' => null, 'used' => 0, 'allowed' => null, 'paid' => null];

        $sub = self::activeFor($business_id, $contact_id);
        if ($sub) {
            self::billSubscription($sub->id, Carbon::today()); // catch up if the daily run was missed
        }

        $period = ClinicSubscriptionPeriod::where('business_id', $business_id)->where('contact_id', $contact_id)
            ->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)
            ->with('subscription.package')->orderByDesc('id')->first();

        if (! $period) {
            if ($sub && $sub->start_date->gt($date)) {
                $out['state'] = 'not_started';
                $out['subscription'] = $sub;
                $out['message'] = __('clinic.plan_state_not_started', ['plan' => $sub->package->name, 'date' => (new Util())->format_date($sub->start_date->toDateString())]);
            }

            return $out;
        }

        $settings = ClinicSettings::all($business_id);
        $plan = optional(optional($period->subscription)->package)->name;
        $out['subscription'] = $period->subscription;
        $out['period'] = $period;
        $out['used'] = $period->visitsUsed();
        $out['allowed'] = $period->visits_allowed;
        $out['paid'] = self::periodPaid($period);

        if ($out['paid']) {
            $out['state'] = 'covered';
        } elseif ($period->period_start->diffInDays($date) < (int) $settings['plan_grace_days']) {
            $out['state'] = 'grace';
        } else {
            $out['state'] = ['charge' => 'unpaid_charge', 'block' => 'unpaid_block', 'cover' => 'unpaid_cover'][$settings['plan_unpaid_rule']] ?? 'unpaid_charge';
        }

        $out['covers'] = in_array($out['state'], ['covered', 'grace', 'unpaid_cover']);
        $out['blocks'] = $out['state'] == 'unpaid_block';

        if ($out['covers'] && $out['allowed'] !== null && $out['used'] >= $out['allowed']) {
            $out['state'] = 'limit_reached';
            $out['covers'] = false;
        }

        $out['message'] = __('clinic.plan_state_'.$out['state'], [
            'plan' => $plan,
            'used' => $out['used'],
            'allowed' => $out['allowed'] ?? '∞',
            'until' => (new Util())->format_date($period->period_end->toDateString()),
        ]);

        return $out;
    }
}
