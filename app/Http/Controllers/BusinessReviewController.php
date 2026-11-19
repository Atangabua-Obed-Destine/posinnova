<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Utils\BusinessReview;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * PosInnova: the periodic reviews I-NNOVA delivers to its clients.
 * - Reports > Financial summary: the Financial Assessment Summary of any period (screen, print, PDF).
 * - Reports > Quarterly stock control: the quarter's Stock Reconciliation Report (screen, print, PDF).
 */
class BusinessReviewController extends Controller
{
    protected $tu;

    protected $review;

    public function __construct(TransactionUtil $tu)
    {
        $this->tu = $tu;
        $this->review = new BusinessReview($tu);
    }

    // ------------------------------------------------------------------ financial summary

    public function financial(Request $request)
    {
        $this->authorizeFinancial();

        return view('business_review.financial', $this->financialData($request));
    }

    public function financialPdf(Request $request)
    {
        $this->authorizeFinancial();
        $data = $this->financialData($request) + ['pdf' => true, 'notes' => mb_substr((string) $request->input('notes'), 0, 5000)];
        $data['logo'] = $this->logo($data['biz'], true);
        $name = 'financial-summary-' . $data['d']['start']->format('Y-m-d') . '_' . $data['d']['end']->format('Y-m-d') . '.pdf';

        return \PDF::loadView('business_review.financial_pdf', $data)->setPaper('a4', 'portrait')->download($name);
    }

    protected function authorizeFinancial()
    {
        abort_unless(auth()->user()->can('profit_loss_report.view'), 403, 'Unauthorized action.');
    }

    protected function financialData(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $presets = BusinessReview::presets();
        $period = (string) $request->input('period', 'this_month');
        if ($period === 'custom') {
            try {
                $start = Carbon::parse((string) $request->input('start'))->startOfDay();
                $end = Carbon::parse((string) $request->input('end'))->startOfDay();
            } catch (\Throwable $e) {
                [$start, $end] = $presets['this_month'];
                $period = 'this_month';
            }
            if ($end->lt($start)) {
                [$start, $end] = [$end, $start];
            }
            // at most 3 years at a time
            if ($start->diffInDays($end) > 1100) {
                $start = $end->copy()->subDays(1100);
            }
        } else {
            $period = isset($presets[$period]) ? $period : 'this_month';
            [$start, $end] = $presets[$period];
        }
        [$location_id, $locations, $permitted] = $this->location($request, $business_id);

        $d = $this->review->financial($business_id, $location_id, $start->format('Y-m-d'), $end->format('Y-m-d'), $permitted, true, $period === 'custom' ? null : $period);
        $biz = Business::find($business_id);

        return [
            'd' => $d, 'tu' => $this->tu, 'biz' => $biz, 'logo' => $this->logo($biz, false),
            'presets' => $presets, 'period' => $period, 'locations' => $locations, 'location_id' => $location_id,
            'location_name' => $location_id ? ($locations[$location_id] ?? '') : (count($locations) > 1 ? __('review.all_locations') : ''),
            'prepared_by' => trim(auth()->user()->first_name . ' ' . auth()->user()->last_name),
        ];
    }

    // ------------------------------------------------------------------ quarterly stock control

    public function stockControl(Request $request)
    {
        $this->authorizeStock();

        return view('business_review.stock_control', $this->stockData($request));
    }

    public function stockControlPdf(Request $request)
    {
        $this->authorizeStock();
        $data = $this->stockData($request) + ['pdf' => true, 'notes' => mb_substr((string) $request->input('notes'), 0, 5000)];
        $data['logo'] = $this->logo($data['biz'], true);
        $name = 'stock-control-' . $data['s']['year'] . '-Q' . $data['s']['q'] . '.pdf';

        return \PDF::loadView('business_review.stock_pdf', $data)->setPaper('a4', 'portrait')->download($name);
    }

    protected function authorizeStock()
    {
        $u = auth()->user();
        abort_unless($u->can('inventory_count.view') || $u->can('inventory_count.create') || $u->can('stock_report.view'), 403, 'Unauthorized action.');
    }

    protected function stockData(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        [$cy, $cq] = BusinessReview::quarterOf(Carbon::today());
        // this quarter and the 11 before it
        $quarters = [];
        for ($i = 0, $y = $cy, $q = $cq; $i < 12; $i++) {
            $quarters[$y . '-' . $q] = __('review.quarter_long', ['q' => $q, 'year' => $y]) . ($i === 0 ? ' · ' . __('review.current') : '');
            if (--$q === 0) {
                $q = 4;
                $y--;
            }
        }
        $key = (string) $request->input('quarter', $cy . '-' . $cq);
        if (! isset($quarters[$key])) {
            $key = $cy . '-' . $cq;
        }
        [$year, $q] = array_map('intval', explode('-', $key));
        [$location_id, $locations, $permitted] = $this->location($request, $business_id);

        $s = $this->review->stockControl($business_id, $location_id, $year, $q, $permitted);
        $biz = Business::find($business_id);

        return [
            's' => $s, 'tu' => $this->tu, 'biz' => $biz, 'logo' => $this->logo($biz, false),
            'quarters' => $quarters, 'quarter_key' => $key, 'locations' => $locations, 'location_id' => $location_id,
            'location_name' => $location_id ? ($locations[$location_id] ?? '') : (count($locations) > 1 ? __('review.all_locations') : ''),
            'prepared_by' => trim(auth()->user()->first_name . ' ' . auth()->user()->last_name),
        ];
    }

    // ------------------------------------------------------------------ shared

    /** [location_id or null, the locations this person may see, permitted] */
    protected function location(Request $request, $business_id)
    {
        $locations = BusinessLocation::forDropdown($business_id)->toArray();
        $permitted = auth()->user()->permitted_locations();
        $location_id = $request->input('location_id');
        $location_id = ($location_id !== null && $location_id !== '' && isset($locations[$location_id])) ? (int) $location_id : null;
        if (count($locations) === 1) {
            $location_id = (int) array_key_first($locations);
        }

        return [$location_id, $locations, $permitted];
    }

    protected function logo($biz, $for_pdf)
    {
        if (! $biz || empty($biz->logo)) {
            return null;
        }
        $file = public_path('uploads/business_logos/' . $biz->logo);
        if (! is_file($file)) {
            return null;
        }

        return $for_pdf ? $file : asset('uploads/business_logos/' . $biz->logo);
    }
}
