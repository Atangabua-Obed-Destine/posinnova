<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Brands;
use App\Category;
use App\InventoryCountSession;
use App\InventoryCountSessionLine;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Unit;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\TaxRate;
use App\Variation;
use App\VariationLocationDetails;
use App\VariationUnitPrice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Inventory Count — Phase 2 (session-based)
 * -----------------------------------------
 * A count session is a persistent, resumable record:
 *
 *   1. User picks a location and clicks "Start New Count" → create()
 *      A session is inserted and ALL active variations at that location are
 *      snapshotted into inventory_count_session_lines (theoretical_at_start).
 *
 *   2. User opens the session (show / sessionData) and enters counted_qty per
 *      line. Each input fires saveLine() to autosave, so a browser crash does
 *      not lose hours of counting.
 *
 *   3. User clicks Post → post()
 *      Diffs are computed as (counted_qty − theoretical_at_start). Shortages
 *      become a stock_adjustment(recount_shortage); surpluses become an
 *      opening_stock. Both share an atomic DB transaction and are linked back
 *      to the session via shortage_transaction_id / surplus_transaction_id.
 *
 * Why theoretical_at_start (not current vld) for diffs?
 *   If sales continue during the count, applying (counted − vld_now) would
 *   record those sales as shrinkage. Using the snapshot makes shrinkage
 *   correctly reflect only what the counter physically observed, and the
 *   final vld self-corrects to physical reality.
 */
class InventoryCountController extends Controller
{
    protected $productUtil;
    protected $transactionUtil;
    protected $moduleUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public static function reasons()
    {
        return [
            'recount_variance' => __('inventory_count.reason_recount_variance'),
            'data_entry_correction' => __('inventory_count.reason_data_entry_correction'),
            'system_discrepancy' => __('inventory_count.reason_system_discrepancy'),
            'other' => __('inventory_count.reason_other'),
        ];
    }

    /**
     * Landing page: drafts + recent posted sessions + Start New Count form.
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('inventory_count.view') && ! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $reasons = self::reasons();

        $can_create = auth()->user()->can('inventory_count.create');
        $can_post = auth()->user()->can('inventory_count.post');
        $can_cancel = auth()->user()->can('inventory_count.cancel');
        $can_approve = auth()->user()->can('inventory_count.approve');

        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);

        $location_id = (int) $request->input('location_id', 0);

        $drafts = collect();
        $pending = collect();
        $recent = collect();
        $recount_queue_count = 0;

        if ($location_id) {
            $permitted = auth()->user()->permitted_locations();
            if ($permitted !== 'all' && ! in_array($location_id, $permitted)) {
                abort(403, 'Unauthorized location.');
            }

            $drafts = InventoryCountSession::where('business_id', $business_id)
                ->where('location_id', $location_id)
                ->where('status', 'draft')
                ->with('startedBy:id,first_name,surname,username')
                ->orderByDesc('started_at')
                ->get();

            $pending = InventoryCountSession::where('business_id', $business_id)
                ->where('location_id', $location_id)
                ->where('status', 'pending_approval')
                ->with(['startedBy:id,first_name,surname,username', 'postedBy:id,first_name,surname,username'])
                ->orderByDesc('posted_at')
                ->get();

            $recent = InventoryCountSession::where('business_id', $business_id)
                ->where('location_id', $location_id)
                ->whereIn('status', ['posted', 'cancelled'])
                ->with([
                    'startedBy:id,first_name,surname,username',
                    'postedBy:id,first_name,surname,username',
                    'approvedBy:id,first_name,surname,username',
                ])
                ->orderByDesc('id')
                ->limit(20)
                ->get();

            // Annotate each posted session with variance line/unit counts.
            $posted_ids = $recent->where('status', 'posted')->pluck('id')->all();
            if (! empty($posted_ids)) {
                $agg = DB::table('inventory_count_session_lines')
                    ->whereIn('session_id', $posted_ids)
                    ->whereNotNull('counted_qty')
                    ->selectRaw('session_id,
                        SUM(CASE WHEN counted_qty - theoretical_at_start < -0.0001 THEN 1 ELSE 0 END) as shortage_lines,
                        SUM(CASE WHEN counted_qty - theoretical_at_start <  -0.0001 THEN ABS(counted_qty - theoretical_at_start) ELSE 0 END) as shortage_units,
                        SUM(CASE WHEN counted_qty - theoretical_at_start >  0.0001 THEN 1 ELSE 0 END) as surplus_lines,
                        SUM(CASE WHEN counted_qty - theoretical_at_start >  0.0001 THEN counted_qty - theoretical_at_start ELSE 0 END) as surplus_units')
                    ->groupBy('session_id')
                    ->get()
                    ->keyBy('session_id');
                foreach ($recent as $r) {
                    $a = $agg->get($r->id);
                    $r->shortage_lines = $a->shortage_lines ?? 0;
                    $r->shortage_units = (float) ($a->shortage_units ?? 0);
                    $r->surplus_lines = $a->surplus_lines ?? 0;
                    $r->surplus_units = (float) ($a->surplus_units ?? 0);
                }
            }
        }

        $recount_queue_count = DB::table('inventory_count_session_lines as l')
            ->join('inventory_count_sessions as s', 's.id', '=', 'l.session_id')
            ->where('s.business_id', $business_id)
            ->where('s.status', 'posted')
            ->whereNotNull('l.flagged_at')
            ->whereNull('l.resolved_at')
            ->when($location_id, fn($q) => $q->where('s.location_id', $location_id))
            ->when(auth()->user()->permitted_locations() !== 'all',
                fn($q) => $q->whereIn('s.location_id', auth()->user()->permitted_locations()))
            ->count();

        return view('inventory_count.index')
            ->with(compact(
                'business_locations', 'reasons',
                'can_create', 'can_post', 'can_cancel', 'can_approve',
                'categories', 'brands',
                'drafts', 'pending', 'recent', 'location_id', 'recount_queue_count'
            ));
    }

    /**
     * Create a new session and snapshot all active variations at the location.
     */
    public function create(Request $request)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'location_id' => 'required|integer',
            'scope_type' => 'nullable|in:all,category,brand',
            'scope_ids' => 'nullable|array',
            'scope_ids.*' => 'integer',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');
        $location_id = (int) $request->input('location_id');

        $scope_type = $request->input('scope_type', 'all');
        $scope_ids = array_values(array_filter((array) $request->input('scope_ids', []), 'is_numeric'));
        $scope_ids = array_map('intval', $scope_ids);
        if ($scope_type === 'all' || empty($scope_ids)) {
            $scope_type = 'all';
            $scope_ids = [];
        }

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($location_id, $permitted)) {
            abort(403, 'Unauthorized location.');
        }

        $base = Variation::query()
            ->join('products as p', 'p.id', '=', 'variations.product_id')
            ->leftJoin('product_locations as plloc', function ($j) use ($location_id) {
                $j->on('plloc.product_id', '=', 'p.id')->where('plloc.location_id', '=', $location_id);
            })
            ->leftJoin('variation_location_details as vld', function ($j) use ($location_id) {
                $j->on('vld.variation_id', '=', 'variations.id')
                  ->where('vld.location_id', '=', $location_id);
            })
            ->where('p.business_id', $business_id)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->whereIn('p.type', ['single', 'variable'])
            ->where(function ($q) {
                $q->whereNotNull('plloc.product_id')
                  ->orWhereNotExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('product_locations as pl2')
                          ->whereColumn('pl2.product_id', 'p.id');
                  });
            });

        $scope_label = null;
        if ($scope_type === 'category') {
            $base->where(function ($q) use ($scope_ids) {
                $q->whereIn('p.category_id', $scope_ids)
                  ->orWhereIn('p.sub_category_id', $scope_ids);
            });
            $scope_label = Category::whereIn('id', $scope_ids)->pluck('name')->implode(', ');
        } elseif ($scope_type === 'brand') {
            $base->whereIn('p.brand_id', $scope_ids);
            $scope_label = Brands::whereIn('id', $scope_ids)->pluck('name')->implode(', ');
        }

        $rows = $base->select([
                'variations.id as variation_id',
                'p.id as product_id',
                DB::raw('COALESCE(vld.qty_available, 0) as theoretical'),
                'variations.default_purchase_price as unit_cost',
            ])
            ->groupBy('variations.id')
            ->get();

        if ($rows->isEmpty()) {
            return redirect()->action([self::class, 'index'], ['location_id' => $location_id])
                ->with('status', ['success' => 0, 'msg' => __('inventory_count.no_countable_items')]);
        }

        DB::beginTransaction();
        try {
            $ref_no = 'IC-' . date('Ymd-His') . '-' . str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);

            $session = InventoryCountSession::create([
                'business_id' => $business_id,
                'location_id' => $location_id,
                'scope_type' => $scope_type,
                'scope_ids' => $scope_type === 'all' ? null : $scope_ids,
                'scope_label' => $scope_label,
                'reference_no' => $ref_no,
                'status' => 'draft',
                'started_by' => $user_id,
                'started_at' => Carbon::now(),
                'lines_total' => 0, // updated after fan-out
                'lines_counted' => 0,
            ]);

            $insert_rows = $this->buildSessionLinesPayload(
                $rows,
                $session->id,
                $business_id,
                $location_id,
                $request
            );

            $now = Carbon::now();
            foreach (array_chunk($insert_rows, 500) as $chunk) {
                // Stamp timestamps (kept out of buildSessionLinesPayload so
                // it remains a pure builder reusable by createRecountSession).
                foreach ($chunk as &$r) {
                    $r['created_at'] = $now;
                    $r['updated_at'] = $now;
                }
                unset($r);
                DB::table('inventory_count_session_lines')->insert($chunk);
            }

            $session->lines_total = count($insert_rows);
            $session->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::emergency('InventoryCount@create '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return redirect()->action([self::class, 'index'], ['location_id' => $location_id])
                ->with('status', ['success' => 0, 'msg' => trans('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'show'], $session->id);
    }

    /**
     * Resume a session: show its count sheet.
     */
    public function show(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.view') && ! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $session = InventoryCountSession::with([
            'location',
            'startedBy:id,first_name,surname,username',
            'postedBy:id,first_name,surname,username',
            'approvedBy:id,first_name,surname,username',
            'rejectedBy:id,first_name,surname,username',
        ])->where('business_id', $business_id)->findOrFail($id);

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            abort(403, 'Unauthorized location.');
        }

        $reasons = self::reasons();
        $can_create = auth()->user()->can('inventory_count.create');
        $can_post = auth()->user()->can('inventory_count.post');
        $can_cancel = auth()->user()->can('inventory_count.cancel');
        $can_approve = auth()->user()->can('inventory_count.approve');
        $can_edit_price = auth()->user()->can('product.update');

        // Variance summary by line count + units (independent of unit_cost,
        // so zero-cost variations still surface in the UI).
        $variance_summary = [
            'shortage_lines' => 0, 'shortage_units' => 0.0,
            'surplus_lines' => 0, 'surplus_units' => 0.0,
            'zero_cost_lines' => 0,
        ];
        if (in_array($session->status, ['posted', 'pending_approval'])) {
            $rows = DB::table('inventory_count_session_lines')
                ->where('session_id', $session->id)
                ->whereNotNull('counted_qty')
                ->selectRaw('counted_qty - theoretical_at_start as diff, unit_cost')
                ->get();
            foreach ($rows as $r) {
                $d = (float) $r->diff;
                if ($d < -0.0001) {
                    $variance_summary['shortage_lines']++;
                    $variance_summary['shortage_units'] += abs($d);
                } elseif ($d > 0.0001) {
                    $variance_summary['surplus_lines']++;
                    $variance_summary['surplus_units'] += $d;
                }
                if (abs($d) > 0.0001 && (float) $r->unit_cost <= 0) {
                    $variance_summary['zero_cost_lines']++;
                }
            }
        }

        return view('inventory_count.session')
            ->with(compact('session', 'reasons', 'can_create', 'can_post', 'can_cancel', 'can_approve', 'can_edit_price', 'variance_summary'));
    }

    /**
     * DataTables source for a session's lines.
     */
    public function sessionData(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.view') && ! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            abort(403, 'Unauthorized location.');
        }

        $editable = $session->status === 'draft' && auth()->user()->can('inventory_count.create');
        $can_edit_price = auth()->user()->can('product.update');

        $query = DB::table('inventory_count_session_lines as l')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('units as u', 'u.id', '=', 'p.unit_id')
            ->join('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
            ->where('l.session_id', $session->id)
            ->select([
                'l.id as line_id',
                'l.variation_id',
                'l.product_id',
                'l.purchase_line_id',
                'l.lot_number',
                'l.exp_date',
                'l.theoretical_at_start',
                'l.counted_qty',
                'l.counted_unit_id',
                'l.counted_qty_input',
                'l.counted_breakdown',
                'l.unit_cost',
                'v.sub_sku as sku',
                'v.name as variation_name',
                'p.name as product',
                'p.type as product_type',
                'p.unit_id as base_unit_id',
                'p.sub_unit_ids as sub_unit_ids_json',
                'p.alert_quantity as alert_quantity',
                'pv.name as product_variation',
                'u.short_name as unit',
                'v.sell_price_inc_tax as unit_sell',
                'cat.name as category',
            ]);

        $filter = $request->input('filter');
        if ($filter === 'counted') {
            $query->whereNotNull('l.counted_qty');
        } elseif ($filter === 'uncounted') {
            $query->whereNull('l.counted_qty');
        } elseif ($filter === 'variance') {
            $query->whereNotNull('l.counted_qty')
                  ->whereRaw('ABS(l.counted_qty - l.theoretical_at_start) > 0.0001');
        }

        if ($request->filled('search_term')) {
            $term = '%' . $request->input('search_term') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('p.name', 'like', $term)
                  ->orWhere('v.sub_sku', 'like', $term)
                  ->orWhere('p.sku', 'like', $term)
                  ->orWhere('cat.name', 'like', $term);
            });
        }

        // Pre-fetch all units for this business so the per-row counted-unit
        // dropdown can be built without N+1 queries.
        $units_by_id = Unit::where('business_id', $business_id)
            ->get(['id', 'short_name', 'base_unit_id', 'base_unit_multiplier'])
            ->keyBy('id');

        return DataTables::of($query)
            ->editColumn('product', function ($row) use ($editable) {
                $name = e($row->product);
                if ($row->product_type === 'variable') {
                    $name .= ' — <small class="text-muted">' . e($row->product_variation . ' / ' . $row->variation_name) . '</small>';
                }
                if ($editable) {
                    $label = $row->product;
                    if ($row->product_type === 'variable' && $row->variation_name) {
                        $label .= ' — ' . $row->variation_name;
                    }
                    $name .= ' <button type="button" class="btn btn-xs btn-link ic-row-add-lot" '
                        . 'data-variation-id="' . (int) $row->variation_id . '" '
                        . 'data-product-id="' . (int) $row->product_id . '" '
                        . 'data-label="' . e($label . ' [' . $row->sku . ']') . '" '
                        . 'title="' . e(__('inventory_count.add_lot')) . '" '
                        . 'style="padding:0 4px;">'
                        . '<i class="fa fa-calendar-plus-o text-warning"></i></button>';
                }
                return $name;
            })
            ->addColumn('lot_exp', function ($row) use ($editable) {
                $lot = $row->lot_number !== null && $row->lot_number !== '' ? e($row->lot_number) : '—';
                $exp = $row->exp_date ? \Carbon\Carbon::parse($row->exp_date)->format('d M Y') : '—';
                $cls = '';
                if ($row->exp_date) {
                    $days = \Carbon\Carbon::parse($row->exp_date)->diffInDays(\Carbon\Carbon::now(), false);
                    // diffInDays(now, false): negative if exp_date is in the past (already expired)
                    if ($days >= 0) {
                        $cls = ' text-danger';
                    } elseif ($days > -30) {
                        $cls = ' text-warning';
                    }
                }
                $exp_iso = $row->exp_date ? \Carbon\Carbon::parse($row->exp_date)->format('Y-m-d') : '';
                $edit_btn = '';
                if ($editable) {
                    $edit_btn = ' <button type="button" class="btn btn-xs btn-primary ic-lot-edit" '
                        . 'data-line-id="' . (int) $row->line_id . '" '
                        . 'data-lot="' . e((string) $row->lot_number) . '" '
                        . 'data-exp="' . e($exp_iso) . '" '
                        . 'data-has-pl="' . ($row->purchase_line_id ? '1' : '0') . '" '
                        . 'title="' . e(__('inventory_count.edit_lot_exp')) . '" '
                        . 'style="margin-left:6px;padding:2px 8px;">'
                        . '<i class="fa fa-pencil"></i> ' . e(__('messages.edit')) . '</button>';
                }
                return '<small class="text-muted">' . __('lang_v1.lot') . ':</small> ' . $lot
                    . '<br><small class="text-muted">' . __('lang_v1.expiry') . ':</small> '
                    . '<span class="' . trim($cls) . '">' . $exp . '</span>'
                    . $edit_btn;
            })
            ->editColumn('unit', function ($row) use ($editable, $can_edit_price, $units_by_id, $business_id) {
                $current = e((string) $row->unit);
                // Only allow inline edit when:
                //   - session is draft & user can edit prices/products
                //   - product has NO sub-units (i.e. single unit mapped = the base unit)
                // Multi-unit products must be edited from the product edit page
                // so unit conversions / purchase records stay consistent.
                $subs = [];
                if (! empty($row->sub_unit_ids_json)) {
                    $decoded = json_decode($row->sub_unit_ids_json, true);
                    if (is_array($decoded)) {
                        $subs = array_values(array_filter($decoded, fn($v) => (int) $v > 0));
                    }
                }
                if (! $editable || ! $can_edit_price || count($subs) > 0) {
                    return $current;
                }
                $opts = '';
                foreach ($units_by_id as $uid => $u) {
                    $sel = ((int) $uid === (int) $row->base_unit_id) ? ' selected' : '';
                    $opts .= '<option value="' . (int) $uid . '"' . $sel . '>' . e($u->short_name) . '</option>';
                }
                return '<select class="form-control input-sm ic-unit-edit" '
                    . 'data-product-id="' . (int) $row->product_id . '" '
                    . 'data-orig="' . (int) $row->base_unit_id . '" '
                    . 'style="min-width:90px;">' . $opts . '</select>';
            })
            ->editColumn('theoretical_at_start', function ($row) {
                return '<span class="theoretical-qty" data-qty="' . (float) $row->theoretical_at_start . '">'
                    . $this->productUtil->num_f((float) $row->theoretical_at_start, false, null, true)
                    . '</span> ' . e($row->unit);
            })
            ->editColumn('alert_quantity', function ($row) use ($editable, $can_edit_price) {
                $has_val = $row->alert_quantity !== null && $row->alert_quantity !== '';
                $val_disp = $has_val
                    ? ($this->productUtil->num_f((float) $row->alert_quantity, false, null, true) . ' ' . e($row->unit))
                    : '<span class="text-muted">—</span>';
                if (! $editable || ! $can_edit_price) {
                    return $val_disp;
                }
                $val = $has_val ? $this->productUtil->num_f((float) $row->alert_quantity, false) : '';
                return '<div class="input-group input-group-sm ic-alert-edit" style="min-width:150px;">'
                    . '<input type="text" class="form-control input-sm input_number ic-alert-input text-right" '
                    . 'data-product-id="' . (int) $row->product_id . '" '
                    . 'data-orig="' . ($has_val ? (float) $row->alert_quantity : '') . '" '
                    . 'value="' . $val . '" placeholder="—" />'
                    . '<span class="input-group-addon">' . e($row->unit) . '</span>'
                    . '<span class="input-group-btn">'
                    . '<button type="button" class="btn btn-primary btn-sm ic-alert-save" '
                    . 'title="' . e(__('messages.save')) . '">'
                    . '<i class="fa fa-check"></i></button>'
                    . '</span></div>';
            })
            ->editColumn('unit_cost', function ($row) use ($editable) {
                $val_disp = $this->productUtil->num_f((float) $row->unit_cost, true);
                if (! $editable) {
                    return $val_disp;
                }
                $val = $this->productUtil->num_f((float) $row->unit_cost, false);
                return '<div class="input-group input-group-sm ic-cost-edit" style="min-width:160px;">'
                    . '<input type="text" class="form-control input-sm input_number ic-cost-input text-right" '
                    . 'data-line-id="' . (int) $row->line_id . '" '
                    . 'data-orig="' . (float) $row->unit_cost . '" '
                    . 'value="' . $val . '" />'
                    . '<span class="input-group-btn">'
                    . '<button type="button" class="btn btn-primary btn-sm ic-cost-save" '
                    . 'title="' . e(__('messages.save')) . '">'
                    . '<i class="fa fa-check"></i></button>'
                    . '</span></div>';
            })
            ->editColumn('unit_sell', function ($row) use ($can_edit_price) {
                if (! $can_edit_price) {
                    return $this->productUtil->num_f((float) $row->unit_sell, true);
                }
                $val = $this->productUtil->num_f((float) $row->unit_sell, false);
                $html = '<div class="input-group input-group-sm ic-sell-edit" style="min-width:170px;">'
                    . '<input type="text" class="form-control input-sm input_number ic-sell-input text-right" '
                    . 'data-variation-id="' . (int) $row->variation_id . '" '
                    . 'data-orig="' . (float) $row->unit_sell . '" '
                    . 'value="' . $val . '" />'
                    . '<span class="input-group-btn">'
                    . '<button type="button" class="btn btn-default btn-sm ic-unit-prices-btn" '
                    . 'data-variation-id="' . (int) $row->variation_id . '" '
                    . 'data-product="' . e($row->product) . '" '
                    . 'title="' . e(__('lang_v1.per_unit_selling_prices')) . '">'
                    . '<i class="fa fa-tags"></i></button>'
                    . '<button type="button" class="btn btn-primary btn-sm ic-sell-save" '
                    . 'title="' . e(__('messages.save')) . '">'
                    . '<i class="fa fa-check"></i></button>'
                    . '</span></div>';
                return $html;
            })
            ->addColumn('margin', function ($row) {
                $cost = (float) $row->unit_cost;
                $sell = (float) $row->unit_sell;
                if ($cost <= 0) {
                    return '<span class="text-muted">-</span>';
                }
                $pct = (($sell - $cost) / $cost) * 100;
                $cls = $pct < 0 ? 'text-danger' : ($pct > 0 ? 'text-success' : 'text-muted');
                return '<span class="' . $cls . '">' . $this->productUtil->num_f($pct, false, null, true) . '%</span>';
            })
            ->addColumn('counted', function ($row) use ($editable, $units_by_id) {
                $readonly = $editable ? '' : 'readonly';
                $disabled = $editable ? '' : 'disabled';

                // Resolve allowed units for this product (base + declared sub-units).
                $base_unit_id = (int) $row->base_unit_id;
                $allowed_ids = [$base_unit_id];
                if (! empty($row->sub_unit_ids_json)) {
                    $decoded = json_decode($row->sub_unit_ids_json, true);
                    if (is_array($decoded)) {
                        $allowed_ids = array_values(array_unique(array_merge(
                            $allowed_ids,
                            array_map('intval', $decoded)
                        )));
                    }
                }

                $base = $units_by_id->get($base_unit_id);
                $base_short = $base ? $base->short_name : ($row->unit ?? '');

                // Always offer the base unit (×1) first.
                $opts = [['id' => $base_unit_id, 'label' => $base_short, 'multiplier' => 1.0]];
                foreach ($allowed_ids as $uid) {
                    if ($uid === $base_unit_id) {
                        continue;
                    }
                    $u = $units_by_id->get($uid);
                    if (! $u || (int) $u->base_unit_id !== $base_unit_id) {
                        continue;
                    }
                    $opts[] = [
                        'id' => (int) $u->id,
                        'label' => $u->short_name,
                        'multiplier' => (float) $u->base_unit_multiplier,
                    ];
                }

                // Default to the unit the counter last used (else base).
                $selected_id = (int) ($row->counted_unit_id ?? $base_unit_id);
                $option_ids = array_column($opts, 'id');
                if (! in_array($selected_id, $option_ids, true)) {
                    $selected_id = $base_unit_id;
                }
                $selected_mult = 1.0;
                foreach ($opts as $o) {
                    if ($o['id'] === $selected_id) {
                        $selected_mult = $o['multiplier'];
                        break;
                    }
                }

                // Display the user's raw input when available, else base qty.
                if ($row->counted_qty === null) {
                    $val_display = '';
                } elseif ($row->counted_qty_input !== null) {
                    $val_display = (float) $row->counted_qty_input;
                } else {
                    $val_display = (float) $row->counted_qty;
                }

                $html = '<div class="counted-cell" data-line-id="' . (int) $row->line_id . '" data-base-unit-id="' . $base_unit_id . '">';
                $html .= '<div class="input-group input-group-sm">'
                    . '<input type="number" step="any" min="0" '
                    . 'class="form-control input-sm actual-qty" '
                    . 'data-line-id="' . (int) $row->line_id . '" '
                    . 'data-variation-id="' . (int) $row->variation_id . '" '
                    . 'data-product-id="' . (int) $row->product_id . '" '
                    . 'data-theoretical="' . (float) $row->theoretical_at_start . '" '
                    . 'data-unit-cost="' . (float) $row->unit_cost . '" '
                    . 'data-base-unit-id="' . $base_unit_id . '" '
                    . 'data-multiplier="' . $selected_mult . '" '
                    . 'value="' . ($val_display === '' ? '' : $val_display) . '" '
                    . $readonly . ' placeholder="—" />';

                if (count($opts) > 1) {
                    $html .= '<select class="form-control input-sm counted-unit" '
                        . 'data-line-id="' . (int) $row->line_id . '" '
                        . 'style="max-width:90px;" ' . $disabled . '>';
                    foreach ($opts as $o) {
                        $sel = $o['id'] === $selected_id ? ' selected' : '';
                        $html .= '<option value="' . $o['id'] . '" '
                            . 'data-multiplier="' . $o['multiplier'] . '"' . $sel . '>'
                            . e($o['label']) . '</option>';
                    }
                    $html .= '</select>';
                    if ($editable) {
                        // Embed unit options as JSON so the JS can spawn extra
                        // qty+unit rows without re-querying the server.
                        $html .= '<span class="input-group-btn">'
                            . '<button type="button" class="btn btn-default btn-sm ic-mix-toggle" '
                            . 'title="Counted in more than one unit? Click + to add another.">'
                            . '<i class="fa fa-plus"></i></button>'
                            . '</span>';
                        $html .= '<script type="application/json" class="ic-unit-opts">'
                            . json_encode($opts) . '</script>';
                    }
                } else {
                    $html .= '<span class="input-group-addon">' . e($base_short) . '</span>';
                }

                $html .= '</div>'; // /.input-group

                // Mixed-unit parts container — hidden until user clicks +.
                $existing_parts = is_array($row->counted_breakdown ?? null)
                    ? $row->counted_breakdown
                    : (is_string($row->counted_breakdown ?? null) ? json_decode($row->counted_breakdown, true) : null);
                $has_parts = is_array($existing_parts) && count($existing_parts) > 0;

                $html .= '<div class="ic-mix-parts" style="margin-top:4px;' . ($has_parts ? '' : 'display:none;') . '">';
                if ($has_parts && count($opts) > 1) {
                    foreach ($existing_parts as $p) {
                        $html .= '<div class="ic-mix-row input-group input-group-sm" style="margin-top:3px;">'
                            . '<input type="number" step="any" min="0" '
                            . 'class="form-control input-sm ic-mix-qty" value="' . (float) ($p['qty'] ?? 0) . '" />'
                            . '<select class="form-control input-sm ic-mix-unit" style="max-width:90px;">';
                        foreach ($opts as $o) {
                            $sel = ((int) $o['id'] === (int) ($p['unit_id'] ?? 0)) ? ' selected' : '';
                            $html .= '<option value="' . $o['id'] . '" data-multiplier="' . $o['multiplier'] . '"' . $sel . '>'
                                . e($o['label']) . '</option>';
                        }
                        $html .= '</select>'
                            . '<span class="input-group-btn">'
                            . '<button type="button" class="btn btn-default btn-sm ic-mix-remove" title="Remove"><i class="fa fa-times"></i></button>'
                            . '</span></div>';
                    }
                    $html .= '<div class="ic-mix-total small text-muted" style="margin-top:2px;"></div>';
                }
                $html .= '</div>'; // /.ic-mix-parts

                $html .= '</div>'; // /.counted-cell
                return $html;
            })
            ->addColumn('variance', function ($row) {
                if ($row->counted_qty === null) {
                    return '<span class="variance-cell text-muted">—</span>';
                }
                $diff = round((float) $row->counted_qty - (float) $row->theoretical_at_start, 4);
                if (abs($diff) < 0.0001) {
                    return '<span class="variance-cell text-success"><i class="fa fa-check"></i></span>';
                }
                $cls = $diff < 0 ? 'text-danger' : 'text-warning';
                $sign = $diff > 0 ? '+' : '';
                return '<span class="variance-cell ' . $cls . '">' . $sign
                    . $this->productUtil->num_f($diff, false, null, true) . '</span>';
            })
            ->rawColumns(['product', 'theoretical_at_start', 'counted', 'variance', 'lot_exp', 'unit', 'unit_cost', 'unit_sell', 'margin', 'alert_quantity'])
            ->make(true);
    }

    /**
     * AJAX autosave for a single line's counted_qty (null = clear).
     *
     * Accepts an optional unit_id so the counter can record qty in any
     * allowed sub-unit (e.g. "5 card-10" for a Tab-based product). The
     * raw entry is kept in counted_qty_input and counted_unit_id for
     * audit + redisplay; counted_qty is always stored in the base unit.
     */
    public function saveLine(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate([
            'line_id' => 'required|integer',
            'counted_qty' => 'nullable|numeric|min:0',
            'unit_id' => 'nullable|integer',
            'breakdown' => 'nullable|array',
            'breakdown.*.qty' => 'required_with:breakdown|numeric|min:0',
            'breakdown.*.unit_id' => 'required_with:breakdown|integer',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.session_not_editable')], 422);
        }

        $line = InventoryCountSessionLine::where('session_id', $session->id)
            ->where('id', $request->input('line_id'))
            ->firstOrFail();

        $product = Product::select(['id', 'unit_id', 'sub_unit_ids'])->find($line->product_id);
        $base_unit_id = $product ? (int) $product->unit_id : null;
        $sub_ids = ($product && is_array($product->sub_unit_ids))
            ? array_map('intval', $product->sub_unit_ids) : [];

        // Helper: resolve a (unit_id) to (multiplier, label) for this product.
        $resolveUnit = function ($req_unit_id) use ($business_id, $base_unit_id, $sub_ids) {
            $req_unit_id = (int) $req_unit_id;
            if ($req_unit_id === $base_unit_id) {
                $u = Unit::where('business_id', $business_id)->find($base_unit_id);
                return [
                    'unit_id' => $base_unit_id,
                    'multiplier' => 1.0,
                    'label' => $u ? $u->short_name : '',
                ];
            }
            if (! in_array($req_unit_id, $sub_ids, true)) {
                return null;
            }
            $u = Unit::where('business_id', $business_id)
                ->where('id', $req_unit_id)
                ->where('base_unit_id', $base_unit_id)
                ->first();
            if (! $u) {
                return null;
            }
            $m = (float) $u->base_unit_multiplier;
            return [
                'unit_id' => (int) $u->id,
                'multiplier' => $m > 0 ? $m : 1.0,
                'label' => $u->short_name,
            ];
        };

        $breakdown_in = $request->input('breakdown');
        $breakdown_out = null;
        $base_qty = null;
        $is_counted = false;
        $primary_input_qty = null;
        $primary_unit_id = null;
        $primary_mult = 1.0;

        if (is_array($breakdown_in) && count($breakdown_in) > 0 && $base_unit_id) {
            // Mixed-unit count. Recompute the base total server-side from
            // the parts (don't trust the JS-computed total).
            $total = 0.0;
            $parts_out = [];
            foreach ($breakdown_in as $p) {
                $q = (float) ($p['qty'] ?? 0);
                if ($q <= 0) continue;
                $resolved = $resolveUnit($p['unit_id'] ?? 0);
                if (! $resolved) {
                    return response()->json(['success' => 0, 'msg' => 'Invalid unit in breakdown'], 422);
                }
                $total += $q * $resolved['multiplier'];
                $parts_out[] = [
                    'qty' => $q,
                    'unit_id' => $resolved['unit_id'],
                    'label' => $resolved['label'],
                    'multiplier' => $resolved['multiplier'],
                ];
            }
            if (count($parts_out) === 0) {
                // Treat as cleared.
                $is_counted = false;
            } else {
                $is_counted = true;
                $base_qty = round($total, 4);
                $breakdown_out = $parts_out;
                // Primary input fields reflect the base total in the base unit.
                $primary_input_qty = $base_qty;
                $primary_unit_id = $base_unit_id;
            }
        } else {
            // Legacy single-unit path.
            $raw = $request->input('counted_qty');
            $is_counted = ! ($raw === null || $raw === '');
            $input_qty = $is_counted ? (float) $raw : null;

            $req_unit_id = $request->input('unit_id');
            $chosen_unit_id = $base_unit_id;
            $multiplier = 1.0;
            if ($req_unit_id !== null && $req_unit_id !== '' && $base_unit_id) {
                $resolved = $resolveUnit($req_unit_id);
                if (! $resolved) {
                    return response()->json(['success' => 0, 'msg' => 'Invalid unit for this product'], 422);
                }
                $chosen_unit_id = $resolved['unit_id'];
                $multiplier = $resolved['multiplier'];
            }
            $base_qty = $is_counted ? round($input_qty * $multiplier, 4) : null;
            $primary_input_qty = $is_counted ? $input_qty : null;
            $primary_unit_id = $chosen_unit_id;
            $primary_mult = $multiplier;
        }

        $was_counted = ! is_null($line->counted_qty);

        $line->counted_qty = $base_qty;
        $line->counted_qty_input = $primary_input_qty;
        $line->counted_unit_id = $is_counted ? $primary_unit_id : null;
        $line->counted_breakdown = $is_counted ? $breakdown_out : null;
        $line->counted_by = $is_counted ? $user_id : null;
        $line->counted_at = $is_counted ? Carbon::now() : null;
        $line->save();

        if ($was_counted !== $is_counted) {
            $session->increment('lines_counted', $is_counted ? 1 : -1);
        }

        $diff = $is_counted ? round($base_qty - (float) $line->theoretical_at_start, 4) : null;

        return response()->json([
            'success' => 1,
            'lines_counted' => $session->lines_counted,
            'lines_total' => $session->lines_total,
            'diff' => $diff,
            'base_qty' => $base_qty,
            'multiplier' => $primary_mult,
            'breakdown' => $breakdown_out,
        ]);
    }

    /**
     * Select2 search of variations already present in this session — used by
     * the "Add Lot" modal so counters can attach a freshly-discovered batch
     * (lot/expiry) to a product already on the count sheet.
     */
    public function variationSearch(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            return response()->json(['results' => []], 403);
        }
        $business_id = $request->session()->get('user.business_id');
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            abort(403);
        }

        $term = trim((string) $request->input('q', ''));

        $q = DB::table('inventory_count_session_lines as l')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('units as u', 'u.id', '=', 'p.unit_id')
            ->where('l.session_id', $session->id)
            ->groupBy('l.variation_id', 'l.product_id', 'p.name', 'v.sub_sku',
                      'v.name', 'p.type', 'p.unit_id', 'p.sub_unit_ids', 'u.short_name')
            ->select([
                'l.variation_id', 'l.product_id',
                'p.name as product', 'v.sub_sku as sku',
                'v.name as variation_name', 'p.type as product_type',
                'p.unit_id as base_unit_id', 'p.sub_unit_ids as sub_unit_ids_json',
                'u.short_name as unit',
                DB::raw('MAX(l.unit_cost) as unit_cost'),
            ])
            ->orderBy('p.name');

        if ($term !== '') {
            $like = '%' . $term . '%';
            $q->where(function ($x) use ($like) {
                $x->where('p.name', 'like', $like)
                  ->orWhere('v.sub_sku', 'like', $like)
                  ->orWhere('p.sku', 'like', $like);
            });
        }

        $rows = $q->limit(30)->get();

        $units = Unit::where('business_id', $business_id)
            ->get(['id', 'short_name', 'base_unit_id', 'base_unit_multiplier'])
            ->keyBy('id');

        $results = $rows->map(function ($r) use ($units) {
            $base_id = (int) $r->base_unit_id;
            $base = $units->get($base_id);
            $opts = [['id' => $base_id, 'label' => $base ? $base->short_name : ($r->unit ?? ''), 'multiplier' => 1.0]];
            $subs = $r->sub_unit_ids_json ? json_decode($r->sub_unit_ids_json, true) : [];
            if (is_array($subs)) {
                foreach ($subs as $sid) {
                    $sid = (int) $sid;
                    if ($sid === $base_id) continue;
                    $u = $units->get($sid);
                    if (! $u || (int) $u->base_unit_id !== $base_id) continue;
                    $opts[] = [
                        'id' => $sid,
                        'label' => $u->short_name,
                        'multiplier' => (float) $u->base_unit_multiplier,
                    ];
                }
            }
            $label = $r->product;
            if ($r->product_type === 'variable' && $r->variation_name) {
                $label .= ' — ' . $r->variation_name;
            }
            $label .= ' [' . $r->sku . ']';
            return [
                'id' => (int) $r->variation_id,
                'text' => $label,
                'product_id' => (int) $r->product_id,
                'unit_cost' => (float) $r->unit_cost,
                'units' => $opts,
            ];
        });

        return response()->json(['results' => $results->values()]);
    }

    /**
     * Add a new lot/batch row to a draft session for a product already in it.
     * Used when staff finds physical stock under a different (or untracked)
     * lot/expiry than what was snapshotted at session start. The new line
     * has theoretical_at_start = 0, counted_qty = entered qty, so on approval
     * it becomes a surplus that creates a new PurchaseLine carrying the
     * lot_number/exp_date — exactly like Opening Stock.
     */
    public function addLot(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate([
            'variation_id' => 'required|integer',
            'counted_qty' => 'required|numeric|gt:0',
            'unit_id' => 'nullable|integer',
            'lot_number' => 'nullable|string|max:191',
            'exp_date' => 'nullable|date',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.session_not_editable')], 422);
        }

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            return response()->json(['success' => 0, 'msg' => 'Unauthorized location'], 403);
        }

        // Restrict to variations already on this session's sheet — keeps
        // scope narrow (we don't yet support adding brand-new products mid-count).
        $existing = InventoryCountSessionLine::where('session_id', $session->id)
            ->where('variation_id', (int) $request->input('variation_id'))
            ->first();
        if (! $existing) {
            return response()->json(['success' => 0, 'msg' => 'Product is not part of this count session'], 422);
        }

        $product = Product::select(['id', 'unit_id', 'sub_unit_ids'])->find($existing->product_id);
        if (! $product) {
            return response()->json(['success' => 0, 'msg' => 'Product not found'], 422);
        }
        $base_unit_id = (int) $product->unit_id;
        $sub_ids = is_array($product->sub_unit_ids)
            ? array_map('intval', $product->sub_unit_ids) : [];

        $req_unit_id = (int) ($request->input('unit_id') ?: $base_unit_id);
        $multiplier = 1.0;
        $chosen_unit_id = $base_unit_id;
        if ($req_unit_id !== $base_unit_id) {
            if (! in_array($req_unit_id, $sub_ids, true)) {
                return response()->json(['success' => 0, 'msg' => 'Invalid unit for this product'], 422);
            }
            $u = Unit::where('business_id', $business_id)
                ->where('id', $req_unit_id)
                ->where('base_unit_id', $base_unit_id)
                ->first();
            if (! $u) {
                return response()->json(['success' => 0, 'msg' => 'Invalid unit for this product'], 422);
            }
            $m = (float) $u->base_unit_multiplier;
            $multiplier = $m > 0 ? $m : 1.0;
            $chosen_unit_id = (int) $u->id;
        }

        $input_qty = (float) $request->input('counted_qty');
        $base_qty = round($input_qty * $multiplier, 4);

        $exp_date_raw = $request->input('exp_date');
        $exp_date = null;
        if (! empty($exp_date_raw)) {
            try {
                $exp_date = Carbon::parse($exp_date_raw)->toDateString();
            } catch (\Exception $e) {
                return response()->json(['success' => 0, 'msg' => 'Invalid expiry date'], 422);
            }
        }

        $unit_cost = $request->input('unit_cost');
        if ($unit_cost === null || $unit_cost === '') {
            $unit_cost = (float) $existing->unit_cost;
            if ($unit_cost <= 0) {
                $unit_cost = $this->lookupUnitCost($existing->variation_id, $session->location_id);
            }
        } else {
            $unit_cost = (float) $unit_cost;
        }

        DB::beginTransaction();
        try {
            $line = new InventoryCountSessionLine();
            $line->session_id = $session->id;
            $line->product_id = $existing->product_id;
            $line->variation_id = $existing->variation_id;
            $line->purchase_line_id = null; // new lot — PurchaseLine created at approval
            $line->lot_number = $request->input('lot_number') ?: null;
            $line->exp_date = $exp_date;
            $line->theoretical_at_start = 0;
            $line->counted_qty = $base_qty;
            $line->counted_qty_input = $input_qty;
            $line->counted_unit_id = $chosen_unit_id;
            $line->unit_cost = $unit_cost;
            $line->counted_by = $user_id;
            $line->counted_at = Carbon::now();
            $line->save();

            $session->increment('lines_total');
            $session->increment('lines_counted');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('InventoryCount@addLot '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json(['success' => 0, 'msg' => trans('messages.something_went_wrong')], 500);
        }

        $session->refresh();

        return response()->json([
            'success' => 1,
            'msg' => __('inventory_count.lot_added_successfully'),
            'line_id' => $line->id,
            'lines_total' => $session->lines_total,
            'lines_counted' => $session->lines_counted,
        ]);
    }

    /**
     * Edit lot_number / exp_date on an existing session line.
     *
     * If the line is already tied to a PurchaseLine, the change is also
     * pushed to that PL so future sales/reports see the correct expiry
     * immediately. Otherwise only the session-line snapshot is updated;
     * if the line later goes through approval with counted_qty > 0 and
     * no PL, the approval flow will promote it into a new PL carrying
     * this lot/expiry (see approve()).
     */
    public function updateLot(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate([
            'line_id' => 'required|integer',
            'lot_number' => 'nullable|string|max:191',
            'exp_date' => 'nullable|date',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.session_not_editable')], 422);
        }

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            return response()->json(['success' => 0, 'msg' => 'Unauthorized location'], 403);
        }

        $line = InventoryCountSessionLine::where('session_id', $session->id)
            ->where('id', $request->input('line_id'))
            ->firstOrFail();

        $lot_number = $request->input('lot_number');
        $lot_number = ($lot_number === '' ? null : $lot_number);

        $exp_raw = $request->input('exp_date');
        $exp_date = null;
        if (! empty($exp_raw)) {
            try {
                $exp_date = Carbon::parse($exp_raw)->toDateString();
            } catch (\Exception $e) {
                return response()->json(['success' => 0, 'msg' => 'Invalid expiry date'], 422);
            }
        }

        DB::beginTransaction();
        try {
            $line->lot_number = $lot_number;
            $line->exp_date = $exp_date;
            $line->save();

            $pl_updated = false;
            if ($line->purchase_line_id) {
                // Only lot/expiry change — these don't affect FIFO qty/cost
                // so it's safe to update even after sales have been mapped.
                $pl = PurchaseLine::find($line->purchase_line_id);
                if ($pl) {
                    $pl->lot_number = $lot_number;
                    $pl->exp_date = $exp_date;
                    $pl->save();
                    $pl_updated = true;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('InventoryCount@updateLot '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json(['success' => 0, 'msg' => trans('messages.something_went_wrong')], 500);
        }

        return response()->json([
            'success' => 1,
            'msg' => __('inventory_count.lot_updated_successfully'),
            'lot_number' => $lot_number,
            'exp_date' => $exp_date,
            'pl_updated' => $pl_updated,
        ]);
    }

    /**
     * Submit a draft session for approval (Phase 5). Records reason/notes
     * and flips status to pending_approval. NO stock adjustments are written
     * until an approver calls approve().
     */
    public function post(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.post')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'reason' => 'required|string|in:' . implode(',', array_keys(self::reasons())),
            'reason_other' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.session_not_editable')], 422);
        }

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            abort(403, 'Unauthorized location.');
        }

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([self::class, 'index']));
        }

        if ($request->input('reason') === 'other') {
            $reason_other = trim((string) $request->input('reason_other'));
            if ($reason_other === '') {
                return response()->json([
                    'success' => 0,
                    'msg' => __('inventory_count.reason_other_required'),
                ], 422);
            }
        }

        // Sanity check: must have at least one variance to approve.
        $lines = InventoryCountSessionLine::where('session_id', $session->id)
            ->whereNotNull('counted_qty')
            ->get();

        $shortage_count = 0;
        $surplus_count = 0;
        foreach ($lines as $line) {
            $diff = round((float) $line->counted_qty - (float) $line->theoretical_at_start, 4);
            if ($diff < -0.0001) $shortage_count++;
            elseif ($diff > 0.0001) $surplus_count++;
        }

        if ($shortage_count === 0 && $surplus_count === 0) {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.no_variances_detected'),
            ], 422);
        }

        $session->status = 'pending_approval';
        $session->reason = $request->input('reason');
        $session->reason_other = $request->input('reason') === 'other' ? $request->input('reason_other') : null;
        $session->notes = $request->input('notes');
        $session->posted_by = $user_id;
        $session->posted_at = Carbon::now();
        // Clear any prior rejection so a re-submission starts clean.
        $session->rejection_notes = null;
        $session->rejected_by = null;
        $session->rejected_at = null;
        $session->save();

        return response()->json([
            'success' => 1,
            'msg' => __('inventory_count.submitted_for_approval', [
                'shortages' => $shortage_count,
                'surpluses' => $surplus_count,
            ]),
            'redirect' => action([self::class, 'index'], ['location_id' => $session->location_id]),
        ]);
    }

    /**
     * Approve a pending session: compute diffs, write txns atomically, mark posted.
     * Requires inventory_count.approve permission. Stock adjustments only happen here.
     */
    public function approve(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.approve')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'approval_notes' => 'nullable|string|max:2000',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'pending_approval') {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.not_pending_approval')], 422);
        }

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            abort(403, 'Unauthorized location.');
        }

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([self::class, 'index']));
        }

        $reason = $session->reason ?: 'other';
        $reason_label = self::reasons()[$reason] ?? $reason;
        if ($reason === 'other' && $session->reason_other) {
            $reason_label .= ': ' . $session->reason_other;
        }

        $location_id = (int) $session->location_id;
        $notes_block = "[Inventory Count {$session->reference_no}] Reason: {$reason_label}"
            . ($session->notes ? "\n" . trim((string) $session->notes) : '');

        DB::beginTransaction();
        try {
            $shortages = [];
            $surpluses = [];
            $promotions = []; // untracked lines with lot/expiry but no variance

            $lines = InventoryCountSessionLine::where('session_id', $session->id)
                ->whereNotNull('counted_qty')
                ->get();

            foreach ($lines as $line) {
                $diff = round((float) $line->counted_qty - (float) $line->theoretical_at_start, 4);

                if (abs($diff) < 0.0001) {
                    // No qty variance. But if the line has no PL backing AND
                    // a lot/expiry was set during the count, we want to
                    // "label" the existing untracked stock by creating a
                    // PurchaseLine carrying that lot/expiry — only when it's
                    // safe to do so (no existing PLs already cover this stock).
                    if (! $line->purchase_line_id
                        && (float) $line->counted_qty > 0
                        && ($line->lot_number !== null || $line->exp_date !== null)) {
                        $promotions[] = [
                            'product_id' => (int) $line->product_id,
                            'variation_id' => (int) $line->variation_id,
                            'quantity' => (float) $line->counted_qty,
                            'lot_number' => $line->lot_number,
                            'exp_date' => $line->exp_date,
                            'unit_cost' => (float) $line->unit_cost,
                        ];
                    }
                    continue;
                }

                $entry = [
                    'product_id' => (int) $line->product_id,
                    'variation_id' => (int) $line->variation_id,
                    'quantity' => abs($diff),
                    'purchase_line_id' => $line->purchase_line_id ? (int) $line->purchase_line_id : null,
                    'lot_number' => $line->lot_number,
                    'exp_date' => $line->exp_date,
                    'unit_cost' => (float) $line->unit_cost,
                ];

                if ($diff < 0) {
                    $shortages[] = $entry;
                } else {
                    $surpluses[] = $entry;
                }
            }

            if (empty($shortages) && empty($surpluses) && empty($promotions)) {
                DB::rollBack();
                return response()->json([
                    'success' => 0,
                    'msg' => __('inventory_count.no_variances_detected'),
                ], 422);
            }

            $business = [
                'id' => $business_id,
                'accounting_method' => $request->session()->get('business.accounting_method'),
                'location_id' => $location_id,
            ];

            $shortage_txn_id = null;
            $surplus_txn_id = null;
            $shortage_value = 0.0;
            $surplus_value = 0.0;

            if (! empty($shortages)) {
                [$shortage_txn_id, $shortage_value] = $this->createShortageTransaction(
                    $business_id, $user_id, $location_id, $shortages, $notes_block, $business
                );
            }

            if (! empty($surpluses)) {
                [$surplus_txn_id, $surplus_value] = $this->createSurplusTransaction(
                    $business_id, $user_id, $location_id, $surpluses, $notes_block, $session->reference_no
                );
            }

            // Promote untracked lines (no PL, no variance, but lot/expiry set)
            // into proper PurchaseLines so future sales/reports see the batch.
            // Only safe when there are no existing PLs for that variation at
            // this location with remaining qty — otherwise creating a new PL
            // would double-count the same physical stock.
            $promoted_count = 0;
            if (! empty($promotions)) {
                foreach ($promotions as $p) {
                    $existing_pl_qty = (float) DB::table('purchase_lines as pl')
                        ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                        ->where('pl.variation_id', $p['variation_id'])
                        ->whereRaw('COALESCE(pl.location_id, t.location_id) = ?', [$location_id])
                        ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
                        ->whereRaw('COALESCE(pl.quantity, 0) - COALESCE(pl.quantity_sold, 0) - COALESCE(pl.quantity_adjusted, 0) - COALESCE(pl.quantity_returned, 0) > 0')
                        ->sum(DB::raw('COALESCE(pl.quantity, 0) - COALESCE(pl.quantity_sold, 0) - COALESCE(pl.quantity_adjusted, 0) - COALESCE(pl.quantity_returned, 0)'));

                    if ($existing_pl_qty > 0.0001) {
                        // Stock is already PL-backed elsewhere; skip to avoid
                        // double counting. The user must use Add Lot instead.
                        continue;
                    }

                    $cost = $p['unit_cost'] > 0
                        ? $p['unit_cost']
                        : $this->lookupUnitCost($p['variation_id'], $location_id);

                    $promo_ref = 'IC-LBL/' . $session->reference_no . '/' . $p['variation_id'];
                    $promo_txn = Transaction::create([
                        'business_id' => $business_id,
                        'location_id' => $location_id,
                        'type' => 'opening_stock',
                        'status' => 'received',
                        'opening_stock_product_id' => $p['product_id'],
                        'ref_no' => $promo_ref,
                        'transaction_date' => Carbon::now(),
                        'total_before_tax' => $cost * $p['quantity'],
                        'final_total' => $cost * $p['quantity'],
                        'payment_status' => 'paid',
                        'additional_notes' => $notes_block . "\n[Labeled untracked stock with lot/expiry]",
                        'created_by' => $user_id,
                    ]);

                    $pl = new PurchaseLine();
                    $pl->transaction_id = $promo_txn->id;
                    $pl->product_id = $p['product_id'];
                    $pl->variation_id = $p['variation_id'];
                    $pl->location_id = $location_id;
                    $pl->quantity = $p['quantity'];
                    $pl->pp_without_discount = $cost;
                    $pl->purchase_price = $cost;
                    $pl->purchase_price_inc_tax = $cost;
                    $pl->item_tax = 0;
                    $pl->quantity_sold = 0;
                    $pl->quantity_adjusted = 0;
                    $pl->quantity_returned = 0;
                    $pl->lot_number = $p['lot_number'];
                    $pl->exp_date = $p['exp_date'];
                    $pl->save();
                    // Note: NO VLD increment. The stock already exists in
                    // qty_available; we're just labeling it.

                    $promoted_count++;
                }
            }

            $session->status = 'posted';
            $session->approved_by = $user_id;
            $session->approved_at = Carbon::now();
            $session->approval_notes = $request->input('approval_notes');
            $session->shortage_transaction_id = $shortage_txn_id;
            $session->surplus_transaction_id = $surplus_txn_id;
            $session->shortage_value = $shortage_value;
            $session->surplus_value = $surplus_value;
            $session->save();

            // Phase 6: flag high-variance lines from THIS session, then resolve
            // any pre-existing flagged-unresolved lines whose variation_id was
            // recounted here.
            [$flagged, $resolved] = $this->updateRecountQueue($session);

            DB::commit();

            return response()->json([
                'success' => 1,
                'msg' => __('inventory_count.inventory_count_posted_successfully', [
                    'shortages' => count($shortages),
                    'surpluses' => count($surpluses),
                ]),
                'redirect' => action([self::class, 'index'], ['location_id' => $location_id]),
                'shortage_txn_id' => $shortage_txn_id,
                'surplus_txn_id' => $surplus_txn_id,
                'shortage_value' => $shortage_value,
                'surplus_value' => $surplus_value,
                'flagged_for_recount' => $flagged,
                'recount_queue_resolved' => $resolved,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('InventoryCount@approve '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            $msg = trans('messages.something_went_wrong');
            if (get_class($e) === \App\Exceptions\PurchaseSellMismatch::class) {
                $msg = $e->getMessage();
            }
            return response()->json(['success' => 0, 'msg' => $msg], 500);
        }
    }

    /**
     * Reject a pending session: sends it back to draft so the counter can fix
     * issues and resubmit. A rejection note is required for audit trail.
     */
    public function reject(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.approve')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'rejection_notes' => 'required|string|max:2000',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'pending_approval') {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.not_pending_approval')], 422);
        }

        $session->status = 'draft';
        $session->rejected_by = $user_id;
        $session->rejected_at = Carbon::now();
        $session->rejection_notes = $request->input('rejection_notes');
        // Clear submission metadata so the next post() treats it as a fresh submit.
        $session->posted_by = null;
        $session->posted_at = null;
        $session->save();

        return response()->json([
            'success' => 1,
            'msg' => __('inventory_count.session_rejected'),
            'redirect' => action([self::class, 'index'], ['location_id' => $session->location_id]),
        ]);
    }

    /**
     * Cancel a draft or pending_approval session. Approvers can cancel
     * submitted sessions (e.g. obviously bad counts) without going through reject.
     */
    public function cancel(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.cancel')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if (! in_array($session->status, ['draft', 'pending_approval'])) {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.session_not_editable')], 422);
        }

        $session->status = 'cancelled';
        $session->cancelled_by = $user_id;
        $session->cancelled_at = Carbon::now();
        $session->save();

        return response()->json([
            'success' => 1,
            'msg' => __('inventory_count.session_cancelled'),
            'redirect' => action([self::class, 'index'], ['location_id' => $session->location_id]),
        ]);
    }

    /**
     * Blank count sheet PDF — paper-first workflow. Pass ?blind=1 to hide
     * theoretical quantities for double-blind counts. Pass ?session_id=N to
     * print exactly the rows of an existing session (respects its scope and
     * lot fan-out) instead of the whole location.
     */
    public function blankPdf(Request $request)
    {
        if (! auth()->user()->can('inventory_count.view') && ! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'location_id' => 'nullable|integer',
            'session_id'  => 'nullable|integer',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $session = null;

        if ($request->filled('session_id')) {
            $session = InventoryCountSession::where('business_id', $business_id)
                ->findOrFail((int) $request->input('session_id'));
            $location_id = (int) $session->location_id;
        } else {
            if (! $request->filled('location_id')) {
                abort(422, 'location_id or session_id is required.');
            }
            $location_id = (int) $request->input('location_id');
        }

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($location_id, $permitted)) {
            abort(403, 'Unauthorized location.');
        }

        $location = BusinessLocation::where('business_id', $business_id)->findOrFail($location_id);

        if ($session !== null) {
            // Print exactly the session's lines so the printed sheet matches
            // the on-screen session (already fanned out per lot/batch).
            $q = DB::table('inventory_count_session_lines as l')
                ->join('variations', 'variations.id', '=', 'l.variation_id')
                ->join('products as p', 'p.id', '=', 'l.product_id')
                ->join('units as u', 'u.id', '=', 'p.unit_id')
                ->join('product_variations as pv', 'pv.id', '=', 'variations.product_variation_id')
                ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
                ->where('l.session_id', $session->id)
                ->select([
                    'variations.id as variation_id',
                    'variations.sub_sku as sku',
                    'p.name as product',
                    'p.type as product_type',
                    'pv.name as product_variation',
                    'variations.name as variation_name',
                    'u.short_name as unit',
                    'p.unit_id as base_unit_id',
                    'p.sub_unit_ids as sub_unit_ids_json',
                    'l.lot_number',
                    'l.exp_date',
                    'cat.name as category',
                    DB::raw('l.theoretical_at_start as theoretical'),
                ]);

            // Mirror the on-screen DataTable filter so the printed sheet
            // contains exactly the rows the operator sees.
            $filter = $request->input('filter');
            if ($filter === 'counted') {
                $q->whereNotNull('l.counted_qty');
            } elseif ($filter === 'uncounted') {
                $q->whereNull('l.counted_qty');
            } elseif ($filter === 'variance') {
                $q->whereNotNull('l.counted_qty')
                  ->whereRaw('ABS(l.counted_qty - l.theoretical_at_start) > 0.0001');
            }

            if ($request->filled('search_term')) {
                $term = '%' . $request->input('search_term') . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('p.name', 'like', $term)
                       ->orWhere('variations.sub_sku', 'like', $term)
                       ->orWhere('p.sku', 'like', $term)
                       ->orWhere('cat.name', 'like', $term);
                });
            }

            // Order to match the DataTable sort (whitelisted column => SQL).
            $order_map = [
                'sku'         => 'variations.sub_sku',
                'product'     => 'p.name',
                'category'    => 'cat.name',
                'unit'        => 'u.short_name',
                'theoretical' => 'l.theoretical_at_start',
                'expiry'      => 'l.exp_date',
            ];
            $order_by = $request->input('order_by');
            $order_dir = strtolower($request->input('order_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            if ($order_by && isset($order_map[$order_by])) {
                $q->orderBy($order_map[$order_by], $order_dir);
            } else {
                $q->orderBy('p.name')->orderBy('l.exp_date');
            }

            $rows = $q->get();
        } else {
        $rows = Variation::query()
            ->join('products as p', 'p.id', '=', 'variations.product_id')
            ->join('units as u', 'u.id', '=', 'p.unit_id')
            ->join('product_variations as pv', 'pv.id', '=', 'variations.product_variation_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
            ->leftJoin('product_locations as plloc', function ($j) use ($location_id) {
                $j->on('plloc.product_id', '=', 'p.id')->where('plloc.location_id', '=', $location_id);
            })
            ->leftJoin('variation_location_details as vld', function ($j) use ($location_id) {
                $j->on('vld.variation_id', '=', 'variations.id')->where('vld.location_id', '=', $location_id);
            })
            ->where('p.business_id', $business_id)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->whereIn('p.type', ['single', 'variable'])
            ->where(function ($q) {
                $q->whereNotNull('plloc.product_id')
                  ->orWhereNotExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('product_locations as pl2')
                          ->whereColumn('pl2.product_id', 'p.id');
                  });
            })
            ->select([
                'variations.id as variation_id',
                'variations.sub_sku as sku',
                'p.name as product',
                'p.type as product_type',
                'pv.name as product_variation',
                'variations.name as variation_name',
                'u.short_name as unit',
                'p.unit_id as base_unit_id',
                'p.sub_unit_ids as sub_unit_ids_json',
                'cat.name as category',
                DB::raw('COALESCE(vld.qty_available, 0) as theoretical'),
            ])
            ->orderBy('p.name')
            ->groupBy('variations.id')
            ->get();
        }

        // Resolve sub-unit short names so the printed sheet can tell the
        // counter "you may also count in card-10 (=10 Tab)" — otherwise they
        // have no way to know which alternative units the system will accept.
        $units_by_id = Unit::where('business_id', $business_id)
            ->get(['id', 'short_name', 'base_unit_id', 'base_unit_multiplier'])
            ->keyBy('id');

        $any_multi_unit = false;
        foreach ($rows as $r) {
            $sub_ids = [];
            if (! empty($r->sub_unit_ids_json)) {
                $decoded = json_decode($r->sub_unit_ids_json, true);
                if (is_array($decoded)) {
                    $sub_ids = array_map('intval', $decoded);
                }
            }
            $alts = [];
            foreach ($sub_ids as $uid) {
                if ($uid === (int) $r->base_unit_id) continue;
                $u = $units_by_id->get($uid);
                if (! $u || (int) $u->base_unit_id !== (int) $r->base_unit_id) continue;
                $mult = (float) $u->base_unit_multiplier;
                if ($mult <= 0) continue;
                $alts[] = $u->short_name . ' (×' . rtrim(rtrim(number_format($mult, 4, '.', ''), '0'), '.') . ')';
            }
            $r->unit_alternatives = $alts;
            if (! empty($alts)) {
                $any_multi_unit = true;
            }
        }

        $hide_theoretical = $request->boolean('blind');

        // Fan out per-batch when lot tracking is enabled so the printed
        // sheet matches what the on-screen session shows. Skip in session
        // mode — rows already come pre-fanned-out from session_lines.
        if ($session === null && $this->isLotTrackingEnabled($request) && $rows->isNotEmpty()) {
            $batches = $this->fetchActiveBatches(
                $rows->pluck('variation_id')->all(),
                $business_id,
                $location_id,
                $request
            );
            $expanded = collect();
            foreach ($rows as $r) {
                $vbatches = $batches[$r->variation_id] ?? collect();
                if ($vbatches->isEmpty()) {
                    $r->lot_number = null;
                    $r->exp_date = null;
                    $expanded->push($r);
                } else {
                    foreach ($vbatches as $b) {
                        $copy = clone $r;
                        $copy->lot_number = $b->lot_number;
                        $copy->exp_date = $b->exp_date;
                        $copy->theoretical = $b->qty_available;
                        $expanded->push($copy);
                    }
                }
            }
            $rows = $expanded;
        }

        $order_labels = [
            'sku' => 'SKU', 'product' => 'Product', 'category' => 'Category',
            'unit' => 'Unit', 'theoretical' => 'Theoretical Stock', 'expiry' => 'Expiry',
        ];
        $active_order = null;
        if ($request->filled('order_by') && isset($order_labels[$request->input('order_by')])) {
            $dir = strtolower($request->input('order_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $active_order = $order_labels[$request->input('order_by')] . ' (' . strtoupper($dir) . ')';
        }

        $pdf = \PDF::loadView('inventory_count.blank_pdf', [
            'location' => $location,
            'rows' => $rows,
            'hide_theoretical' => $hide_theoretical,
            'lot_tracking' => $this->isLotTrackingEnabled($request),
            'any_multi_unit' => $any_multi_unit,
            'generated_at' => Carbon::now(),
            'active_filter' => $request->input('filter'),
            'active_search' => $request->input('search_term'),
            'active_order'  => $active_order,
        ])->setPaper('a4', 'portrait');

        $filename = 'count-sheet-' . preg_replace('/[^A-Za-z0-9]+/', '-', $location->name) . '-' . date('Ymd-His') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * One stock_adjustment transaction containing all shortage lines.
     * When a line carries a purchase_line_id (lot-tracked), it is recorded
     * in stock_adjustment_lines.lot_no_line_id so mapPurchaseSell decrements
     * that specific batch — matching how the Stock Adjustment form behaves.
     */
    protected function createShortageTransaction($business_id, $user_id, $location_id, array $lines, $notes, array $business)
    {
        $ref_count = $this->productUtil->setAndGetReferenceCount('stock_adjustment');
        $ref_no = $this->productUtil->generateReferenceNumber('stock_adjustment', $ref_count);

        $total_value = 0.0;
        $adj_lines = [];

        foreach ($lines as $l) {
            // Prefer the snapshotted batch cost (matches the lot being decremented);
            // fall back to last-purchase lookup when no batch is tied.
            $cost = isset($l['unit_cost']) && (float) $l['unit_cost'] > 0
                ? (float) $l['unit_cost']
                : $this->lookupUnitCost($l['variation_id'], $location_id);
            $total_value += $cost * $l['quantity'];
            $adj_lines[] = [
                'product_id' => $l['product_id'],
                'variation_id' => $l['variation_id'],
                'quantity' => $l['quantity'],
                'unit_price' => $cost,
                'lot_no_line_id' => $l['purchase_line_id'] ?? null,
            ];

            $this->productUtil->decreaseProductQuantity(
                $l['product_id'],
                $l['variation_id'],
                $location_id,
                $l['quantity']
            );
        }

        $txn = Transaction::create([
            'business_id' => $business_id,
            'location_id' => $location_id,
            'type' => 'stock_adjustment',
            'status' => 'received',
            'adjustment_type' => 'recount_shortage',
            'ref_no' => $ref_no,
            'transaction_date' => Carbon::now(),
            'total_amount_recovered' => 0,
            'final_total' => $total_value,
            'additional_notes' => $notes,
            'created_by' => $user_id,
        ]);

        $txn->stock_adjustment_lines()->createMany($adj_lines);

        $this->transactionUtil->mapPurchaseSell($business, $txn->stock_adjustment_lines, 'stock_adjustment');

        return [$txn->id, $total_value];
    }

    /**
     * One opening_stock transaction with new PurchaseLines for surpluses.
     * When a surplus line came from a known batch, the new PurchaseLine
     * carries the same lot_number/exp_date so future sales/adjustments
     * keep the same batch identity. When lot tracking is off (or the source
     * was an untracked row), lot_number/exp_date stay NULL.
     */
    protected function createSurplusTransaction($business_id, $user_id, $location_id, array $lines, $notes, $session_ref)
    {
        $ref_no = 'RC+/' . $business_id . '/' . $location_id . '/' . date('Ymd-His');

        $txn = Transaction::create([
            'business_id' => $business_id,
            'location_id' => $location_id,
            'type' => 'opening_stock',
            'status' => 'received',
            'opening_stock_product_id' => $lines[0]['product_id'],
            'ref_no' => $ref_no,
            'transaction_date' => Carbon::now(),
            'total_before_tax' => 0,
            'final_total' => 0,
            'payment_status' => 'paid',
            'additional_notes' => $notes,
            'created_by' => $user_id,
        ]);

        $total_value = 0.0;

        foreach ($lines as $l) {
            $cost = isset($l['unit_cost']) && (float) $l['unit_cost'] > 0
                ? (float) $l['unit_cost']
                : $this->lookupUnitCost($l['variation_id'], $location_id);
            $qty = $l['quantity'];

            $pl = new PurchaseLine();
            $pl->transaction_id = $txn->id;
            $pl->product_id = $l['product_id'];
            $pl->variation_id = $l['variation_id'];
            $pl->location_id = $location_id;
            $pl->quantity = $qty;
            $pl->pp_without_discount = $cost;
            $pl->purchase_price = $cost;
            $pl->purchase_price_inc_tax = $cost;
            $pl->item_tax = 0;
            $pl->quantity_sold = 0;
            $pl->quantity_adjusted = 0;
            $pl->quantity_returned = 0;
            $pl->lot_number = $l['lot_number'] ?? null;
            $pl->exp_date = $l['exp_date'] ?? null;
            $pl->save();

            $variation = Variation::find($l['variation_id']);
            $vld = VariationLocationDetails::firstOrCreate(
                [
                    'variation_id' => $l['variation_id'],
                    'product_id' => $l['product_id'],
                    'location_id' => $location_id,
                ],
                [
                    'product_variation_id' => $variation->product_variation_id,
                    'qty_available' => 0,
                ]
            );
            $vld->increment('qty_available', $qty);

            $total_value += $cost * $qty;
        }

        $txn->total_before_tax = $total_value;
        $txn->final_total = $total_value;
        $txn->save();

        return [$txn->id, $total_value];
    }

    /**
     * Defensible unit cost (last purchase at location → variation default → 0).
     */
    protected function lookupUnitCost($variation_id, $location_id)
    {
        $row = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('pl.variation_id', $variation_id)
            ->whereRaw('COALESCE(pl.location_id, t.location_id) = ?', [$location_id])
            ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('pl.id')
            ->select('pl.purchase_price')
            ->first();

        if ($row && (float) $row->purchase_price > 0) {
            return (float) $row->purchase_price;
        }

        $v = Variation::find($variation_id);
        if ($v && (float) $v->default_purchase_price > 0) {
            return (float) $v->default_purchase_price;
        }

        return 0.0;
    }

    /**
     * Whether the current business tracks lot numbers and/or expiry dates.
     * When TRUE, count sessions snapshot one row per PurchaseLine batch
     * (mirroring Stock Adjustment + Sell behaviour) instead of one row per
     * variation. Computed from session flags hydrated at login.
     */
    protected function isLotTrackingEnabled(Request $request = null)
    {
        $session = $request ? $request->session() : request()->session();
        return (int) $session->get('business.enable_lot_number') === 1
            || (int) $session->get('business.enable_product_expiry') === 1;
    }

    /**
     * Bulk-fetch active PurchaseLine batches (qty_available > 0) for the given
     * variation_ids at the given location, mirroring the rules used by
     * TransactionUtil::getLotNumbersFromVariation so what the inventory count
     * sees matches what Stock Adjustment and the sell screen see.
     *
     * Returns: Collection grouped by variation_id; each item is an object with
     *   purchase_line_id, lot_number, exp_date, qty_available, unit_cost.
     */
    protected function fetchActiveBatches(array $variation_ids, $business_id, $location_id, Request $request = null)
    {
        if (empty($variation_ids)) {
            return collect();
        }

        $enable_exp = $request
            ? (int) $request->session()->get('business.enable_product_expiry') === 1
            : (int) request()->session()->get('business.enable_product_expiry') === 1;

        $q = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $business_id)
            ->whereRaw('COALESCE(pl.location_id, t.location_id) = ?', [$location_id])
            ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
            ->where('t.status', 'received')
            ->whereIn('pl.variation_id', $variation_ids)
            ->whereRaw('(pl.quantity_sold + pl.quantity_adjusted + pl.quantity_returned) < pl.quantity');

        // When expiry tracking is off, follow the same convention as
        // TransactionUtil::getLotNumbersFromVariation and ignore unlabeled batches.
        if (! $enable_exp) {
            $q->whereNotNull('pl.lot_number');
        }

        return $q->orderByRaw('pl.exp_date IS NULL') // expiring first, NULL last
            ->orderBy('pl.exp_date')
            ->orderBy('pl.id')
            ->select([
                'pl.id as purchase_line_id',
                'pl.variation_id',
                'pl.lot_number',
                'pl.exp_date',
                DB::raw('(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned) as qty_available'),
                'pl.purchase_price_inc_tax as unit_cost',
            ])
            ->get()
            ->groupBy('variation_id');
    }

    /**
     * Build the inventory_count_session_lines insert payload for a set of
     * variation rows. When lot tracking is enabled, fan out one row per
     * active PurchaseLine batch; otherwise one row per variation as before.
     *
     * If a lot-tracked variation has NO active batches at all (e.g. legacy
     * pre-lot opening stock with no lot_number while expiry is off), we
     * fall back to a single null-lot row so the variation is still countable.
     *
     * @param  iterable  $variation_rows  each item needs ->variation_id, ->product_id, ->theoretical, ->unit_cost
     */
    protected function buildSessionLinesPayload($variation_rows, $session_id, $business_id, $location_id, Request $request = null)
    {
        $variation_rows = collect($variation_rows);
        $insert = [];

        $lot_tracking = $this->isLotTrackingEnabled($request);
        $batches_by_variation = $lot_tracking
            ? $this->fetchActiveBatches($variation_rows->pluck('variation_id')->all(), $business_id, $location_id, $request)
            : collect();

        foreach ($variation_rows as $r) {
            $variation_id = (int) $r->variation_id;
            $product_id = (int) $r->product_id;
            $default_cost = (float) ($r->unit_cost ?? 0);
            $vld_qty = (float) ($r->theoretical ?? 0);

            $batches = $lot_tracking ? ($batches_by_variation[$variation_id] ?? collect()) : collect();

            if ($batches->isNotEmpty()) {
                foreach ($batches as $b) {
                    $insert[] = [
                        'session_id' => $session_id,
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'purchase_line_id' => (int) $b->purchase_line_id,
                        'lot_number' => $b->lot_number,
                        'exp_date' => $b->exp_date,
                        'theoretical_at_start' => (float) $b->qty_available,
                        'unit_cost' => (float) $b->unit_cost > 0 ? (float) $b->unit_cost : $default_cost,
                        'counted_qty' => null,
                    ];
                }
            } else {
                // Either lot tracking is off, or the variation has no labelled
                // batches. Snapshot a single row using vld qty / default cost.
                $insert[] = [
                    'session_id' => $session_id,
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'purchase_line_id' => null,
                    'lot_number' => null,
                    'exp_date' => null,
                    'theoretical_at_start' => $vld_qty,
                    'unit_cost' => $default_cost,
                    'counted_qty' => null,
                ];
            }
        }

        return $insert;
    }

    /**
     * Per-session variance report PDF (audit handoff).
     * Lists every counted line with diffs + shortage/surplus subtotals + signature blocks.
     */
    public function variancePdf(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.view') && ! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $session = InventoryCountSession::with([
            'location',
            'startedBy:id,first_name,surname,username',
            'postedBy:id,first_name,surname,username',
            'approvedBy:id,first_name,surname,username',
        ])->where('business_id', $business_id)->findOrFail($id);

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($session->location_id, $permitted)) {
            abort(403, 'Unauthorized location.');
        }

        $rows = DB::table('inventory_count_session_lines as l')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('units as u', 'u.id', '=', 'p.unit_id')
            ->join('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->where('l.session_id', $session->id)
            ->whereNotNull('l.counted_qty')
            ->whereRaw('ABS(l.counted_qty - l.theoretical_at_start) > 0.0001')
            ->orderBy('p.name')
            ->select([
                'v.sub_sku as sku',
                'p.name as product',
                'p.type as product_type',
                'pv.name as product_variation',
                'v.name as variation_name',
                'u.short_name as unit',
                'l.lot_number',
                'l.exp_date',
                'l.theoretical_at_start',
                'l.counted_qty',
                'l.unit_cost',
                DB::raw('(l.counted_qty - l.theoretical_at_start) as diff'),
                DB::raw('((l.counted_qty - l.theoretical_at_start) * l.unit_cost) as diff_value'),
            ])
            ->get();

        $reasons = self::reasons();
        $reason_label = $session->reason
            ? ($reasons[$session->reason] ?? $session->reason)
                . ($session->reason === 'other' && $session->reason_other ? ': ' . $session->reason_other : '')
            : '';

        $pdf = \PDF::loadView('inventory_count.variance_pdf', [
            'session' => $session,
            'rows' => $rows,
            'reason_label' => $reason_label,
            'lot_tracking' => $this->isLotTrackingEnabled($request),
            'generated_at' => Carbon::now(),
        ])->setPaper('a4', 'portrait');

        $filename = 'variance-' . $session->reference_no . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Phase 4 — Scan-to-jump: resolve a scanned SKU/barcode to a session line.
     * Returns { success, line_id, variation_id, theoretical, counted_qty, product }
     * Frontend uses this to focus the matching row and (in scan-mode) auto-increment.
     */
    public function scanLookup(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate(['sku' => 'required|string|max:191']);

        $business_id = $request->session()->get('user.business_id');
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);

        $sku = trim($request->input('sku'));

        $line = DB::table('inventory_count_session_lines as l')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->where('l.session_id', $session->id)
            ->where(function ($q) use ($sku) {
                $q->where('v.sub_sku', $sku)
                  ->orWhere('p.sku', $sku);
            })
            // Prefer an uncounted line first (so repeat scans roll to the next
            // batch); within that, earliest exp_date wins.
            ->orderByRaw('CASE WHEN l.counted_qty IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('l.exp_date IS NULL')
            ->orderBy('l.exp_date')
            ->orderBy('l.id')
            ->select([
                'l.id as line_id', 'l.variation_id', 'l.product_id',
                'l.purchase_line_id', 'l.lot_number', 'l.exp_date',
                'l.theoretical_at_start', 'l.counted_qty', 'l.unit_cost',
                'v.sub_sku', 'v.name as variation_name',
                'p.name as product', 'p.type as product_type',
                'pv.name as product_variation',
            ])
            ->first();

        if (! $line) {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.scan_not_found', ['sku' => $sku]),
            ], 404);
        }

        return response()->json([
            'success' => 1,
            'line_id' => (int) $line->line_id,
            'variation_id' => (int) $line->variation_id,
            'sku' => $line->sub_sku,
            'product' => $line->product . ($line->product_type === 'variable'
                ? ' — ' . $line->product_variation . ' / ' . $line->variation_name : ''),
            'theoretical' => (float) $line->theoretical_at_start,
            'counted_qty' => $line->counted_qty === null ? null : (float) $line->counted_qty,
            'unit_cost' => (float) $line->unit_cost,
            'lot_number' => $line->lot_number,
            'exp_date' => $line->exp_date,
        ]);
    }

    /**
     * Phase 4 — CSV template download (sku, product, theoretical, counted_qty).
     * Counter fills in counted_qty offline or in Excel, then uploads via csvImport().
     */
    public function csvTemplate(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.view') && ! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);

        $rows = DB::table('inventory_count_session_lines as l')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('units as u', 'u.id', '=', 'p.unit_id')
            ->join('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->where('l.session_id', $session->id)
            ->orderBy('p.name')
            ->orderBy('l.exp_date')
            ->select([
                'l.id as line_id',
                'v.sub_sku as sku', 'p.name as product', 'p.type as product_type',
                'pv.name as product_variation', 'v.name as variation_name',
                'u.short_name as unit',
                'l.lot_number', 'l.exp_date',
                'l.theoretical_at_start', 'l.counted_qty',
            ])
            ->get();

        $filename = 'count-template-' . $session->reference_no . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $fh = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($fh, "\xEF\xBB\xBF");
            // line_id is the unambiguous key (one row per batch when lot
            // tracking is on). sku/lot/expiry are reference-only; do not
            // edit them in the spreadsheet — only counted_qty.
            fputcsv($fh, ['line_id', 'sku', 'product', 'unit', 'lot_number', 'exp_date', 'theoretical', 'counted_qty']);
            foreach ($rows as $r) {
                $name = $r->product;
                if ($r->product_type === 'variable') {
                    $name .= ' [' . $r->product_variation . ' / ' . $r->variation_name . ']';
                }
                fputcsv($fh, [
                    (int) $r->line_id,
                    $r->sku,
                    $name,
                    $r->unit,
                    $r->lot_number,
                    $r->exp_date,
                    (float) $r->theoretical_at_start,
                    $r->counted_qty === null ? '' : (float) $r->counted_qty,
                ]);
            }
            fclose($fh);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Phase 4 — Bulk import counted_qty values from a CSV upload.
     * CSV must have a header row with at least: sku, counted_qty.
     * Blank counted_qty cells are skipped (no overwrite). Returns a per-row report.
     */
    public function csvImport(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:5120']);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json(['success' => 0, 'msg' => __('inventory_count.session_not_editable')], 422);
        }

        $path = $request->file('csv_file')->getRealPath();
        $fh = fopen($path, 'r');
        if (! $fh) {
            return response()->json(['success' => 0, 'msg' => 'Cannot read file'], 422);
        }

        // Strip UTF-8 BOM
        $first = fread($fh, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        $header = fgetcsv($fh);
        if (! $header) {
            fclose($fh);
            return response()->json(['success' => 0, 'msg' => 'Empty CSV'], 422);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $idx_line = array_search('line_id', $header, true);
        $idx_sku = array_search('sku', $header, true);
        $idx_qty = array_search('counted_qty', $header, true);
        // Must have counted_qty AND at least one of (line_id, sku) to identify the row.
        if ($idx_qty === false || ($idx_line === false && $idx_sku === false)) {
            fclose($fh);
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.csv_missing_columns'),
            ], 422);
        }

        // Pre-load lookup maps for the session.
        //  - lineIdSet: validates a line_id actually belongs to this session.
        //  - skuMap: SKU → [line_id, ...]. When lot tracking is on a SKU can
        //    map to multiple batches; SKU-only updates are then ambiguous and
        //    we reject them rather than silently picking the wrong batch.
        $lineIdSet = DB::table('inventory_count_session_lines')
            ->where('session_id', $session->id)
            ->pluck('id')
            ->flip()
            ->all();

        $skuRows = DB::table('inventory_count_session_lines as l')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->where('l.session_id', $session->id)
            ->select('v.sub_sku', 'l.id')
            ->get();
        $skuMap = [];
        foreach ($skuRows as $sr) {
            $skuMap[$sr->sub_sku][] = (int) $sr->id;
        }

        $applied = 0;
        $cleared = 0;
        $skipped = 0;
        $invalid = 0;
        $ambiguous = 0;
        $unmatched = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($fh)) !== false) {
                $max_idx = max(array_filter([$idx_line, $idx_sku, $idx_qty], fn ($v) => $v !== false));
                if (count($row) <= $max_idx) {
                    $skipped++;
                    continue;
                }
                $raw = trim((string) $row[$idx_qty]);
                $line_id_in = $idx_line !== false ? trim((string) $row[$idx_line]) : '';
                $sku = $idx_sku !== false ? trim((string) $row[$idx_sku]) : '';

                // Resolve line_id (line_id column wins).
                $line_id = null;
                if ($line_id_in !== '' && ctype_digit($line_id_in)) {
                    $cand = (int) $line_id_in;
                    if (isset($lineIdSet[$cand])) {
                        $line_id = $cand;
                    }
                }
                if (! $line_id && $sku !== '') {
                    $candidates = $skuMap[$sku] ?? [];
                    if (count($candidates) === 1) {
                        $line_id = $candidates[0];
                    } elseif (count($candidates) > 1) {
                        // Multi-batch SKU: refuse to guess.
                        $ambiguous++;
                        continue;
                    }
                }
                if (! $line_id) {
                    if (count($unmatched) < 20) {
                        $unmatched[] = $sku !== '' ? $sku : ('line_id:' . $line_id_in);
                    }
                    continue;
                }

                if ($raw === '') {
                    $skipped++;
                    continue;
                }
                if (! is_numeric($raw) || (float) $raw < 0) {
                    $invalid++;
                    continue;
                }

                $line = InventoryCountSessionLine::find($line_id);
                if (! $line) {
                    $skipped++;
                    continue;
                }
                $was_counted = ! is_null($line->counted_qty);
                $line->counted_qty = (float) $raw;
                $line->counted_by = $user_id;
                $line->counted_at = Carbon::now();
                $line->save();
                if (! $was_counted) {
                    $session->increment('lines_counted');
                }
                $applied++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);
            \Log::emergency('InventoryCount@csvImport '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json(['success' => 0, 'msg' => trans('messages.something_went_wrong')], 500);
        }
        fclose($fh);

        return response()->json([
            'success' => 1,
            'applied' => $applied,
            'cleared' => $cleared,
            'skipped' => $skipped,
            'invalid' => $invalid,
            'ambiguous' => $ambiguous,
            'unmatched_count' => count($unmatched),
            'unmatched_sample' => $unmatched,
            'lines_counted' => $session->fresh()->lines_counted,
            'lines_total' => $session->lines_total,
            'msg' => __('inventory_count.csv_import_summary', [
                'applied' => $applied,
                'invalid' => $invalid,
                'unmatched' => count($unmatched),
            ]),
        ]);
    }

    // -----------------------------------------------------------------------
    // Phase 6 — Recount queue
    // -----------------------------------------------------------------------

    /**
     * After a session is approved/posted, scan its lines:
     *  - Flag THIS session's lines whose variance crosses the threshold.
     *  - Resolve any pre-existing flagged-unresolved lines (same business+location)
     *    whose variation_id appears in this session — they have been recounted.
     *
     * @return array{0:int,1:int} [flagged_count, resolved_count]
     */
    protected function updateRecountQueue($session)
    {
        $threshold_pct = (float) config('constants.inventory_count_recount_threshold_pct', 20);
        $threshold_value = (float) config('constants.inventory_count_recount_threshold_value', 10000);

        $now = Carbon::now();
        $flagged = 0;

        $lines = DB::table('inventory_count_session_lines')
            ->where('session_id', $session->id)
            ->whereNotNull('counted_qty')
            ->get();

        $variation_ids = [];
        foreach ($lines as $line) {
            $variation_ids[] = $line->variation_id;

            $diff_qty = (float) $line->counted_qty - (float) $line->theoretical_at_start;
            $diff_value = $diff_qty * (float) $line->unit_cost;
            $abs_qty = abs($diff_qty);
            $abs_val = abs($diff_value);
            $theoretical = abs((float) $line->theoretical_at_start);
            $pct = $theoretical > 0 ? ($abs_qty / $theoretical) * 100 : ($abs_qty > 0 ? 100 : 0);

            $cross_value = $threshold_value > 0 && $abs_val >= $threshold_value;
            $cross_pct = $threshold_pct > 0 && $pct >= $threshold_pct;

            if ($cross_value || $cross_pct) {
                DB::table('inventory_count_session_lines')
                    ->where('id', $line->id)
                    ->update([
                        'flagged_at' => $now,
                        'flagged_diff_qty' => $diff_qty,
                        'flagged_diff_value' => $diff_value,
                    ]);
                $flagged++;
            }
        }

        // Resolve any earlier flagged lines for these variations at the same
        // business + location (this session "recounted" them).
        $resolved = 0;
        if (! empty($variation_ids)) {
            $resolved = DB::table('inventory_count_session_lines as l')
                ->join('inventory_count_sessions as s', 's.id', '=', 'l.session_id')
                ->where('s.business_id', $session->business_id)
                ->where('s.location_id', $session->location_id)
                ->where('l.session_id', '!=', $session->id)
                ->whereIn('l.variation_id', $variation_ids)
                ->whereNotNull('l.flagged_at')
                ->whereNull('l.resolved_at')
                ->update([
                    'l.resolved_at' => $now,
                    'l.resolved_in_session_id' => $session->id,
                    'l.resolved_by' => $session->approved_by,
                    'l.resolution' => 'recounted',
                ]);
        }

        return [$flagged, $resolved];
    }

    /**
     * List flagged-but-unresolved count lines (the recount queue).
     * Filtered by user-accessible locations.
     */
    public function recountQueue(Request $request)
    {
        if (! auth()->user()->can('inventory_count.view')) {
            abort(403, 'Unauthorized');
        }

        $business_id = $request->session()->get('user.business_id');
        $location_id = $request->input('location_id');

        $permitted_locations = auth()->user()->permitted_locations();

        $query = DB::table('inventory_count_session_lines as l')
            ->join('inventory_count_sessions as s', 's.id', '=', 'l.session_id')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 's.location_id')
            ->leftJoin('users as approver', 'approver.id', '=', 's.approved_by')
            ->where('s.business_id', $business_id)
            ->where('s.status', 'posted')
            ->whereNotNull('l.flagged_at')
            ->whereNull('l.resolved_at');

        if ($permitted_locations !== 'all') {
            $query->whereIn('s.location_id', $permitted_locations);
        }
        if (! empty($location_id)) {
            $query->where('s.location_id', $location_id);
        }

        $rows = $query->orderByDesc('l.flagged_at')
            ->select([
                'l.id as line_id',
                'l.variation_id',
                'l.product_id',
                'l.flagged_at',
                'l.flagged_diff_qty',
                'l.flagged_diff_value',
                'l.theoretical_at_start',
                'l.counted_qty',
                'l.unit_cost',
                'v.sub_sku as sku',
                'v.name as variation_name',
                'p.name as product',
                'p.type as product_type',
                'pv.name as product_variation',
                'u.short_name as unit',
                's.id as source_session_id',
                's.reference_no as source_session_ref',
                's.location_id as source_location_id',
                'bl.name as source_location_name',
                'approver.first_name as approver_first',
                'approver.last_name as approver_last',
                'approver.username as approver_username',
            ])
            ->limit(500)
            ->get();

        if ($request->ajax()) {
            return response()->json(['data' => $rows]);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);

        return view('inventory_count.recount_queue', compact('rows', 'business_locations', 'location_id'));
    }

    /**
     * Create a new draft session scoped to a set of flagged queue lines.
     * Validates all selected lines belong to one location.
     */
    public function createRecountSession(Request $request)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            abort(403, 'Unauthorized');
        }

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $line_ids = (array) $request->input('line_ids', []);
        $line_ids = array_values(array_filter(array_map('intval', $line_ids)));

        if (empty($line_ids)) {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.no_lines_selected'),
            ], 422);
        }

        $permitted_locations = auth()->user()->permitted_locations();

        $lines = DB::table('inventory_count_session_lines as l')
            ->join('inventory_count_sessions as s', 's.id', '=', 'l.session_id')
            ->whereIn('l.id', $line_ids)
            ->where('s.business_id', $business_id)
            ->whereNotNull('l.flagged_at')
            ->whereNull('l.resolved_at')
            ->select('l.id', 'l.variation_id', 'l.product_id', 's.location_id', 's.id as source_session_id')
            ->get();

        if ($lines->isEmpty()) {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.no_lines_selected'),
            ], 422);
        }

        $location_ids = $lines->pluck('location_id')->unique()->values();
        if ($location_ids->count() > 1) {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.recount_single_location_only'),
            ], 422);
        }
        $location_id = (int) $location_ids->first();

        if ($permitted_locations !== 'all' && ! in_array($location_id, (array) $permitted_locations)) {
            abort(403, 'Unauthorized location');
        }

        $variation_ids = $lines->pluck('variation_id')->unique()->values()->all();
        $source_session_id = $lines->pluck('source_session_id')->unique()->first();

        DB::beginTransaction();
        try {
            // Build a synthetic session using the same snapshot helper logic.
            $reference_no = 'IC-R-' . date('Ymd-His') . '-' . str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);

            $session = new InventoryCountSession();
            $session->business_id = $business_id;
            $session->location_id = $location_id;
            $session->reference_no = $reference_no;
            $session->status = 'draft';
            $session->scope_type = 'recount';
            $session->scope_ids = json_encode($variation_ids);
            $session->scope_label = __('inventory_count.recount_scope_label', [
                'count' => count($variation_ids),
                'source' => $source_session_id,
            ]);
            $session->started_at = Carbon::now();
            $session->started_by = $user_id;
            $session->notes = __('inventory_count.recount_default_notes', ['source' => $source_session_id]);
            $session->save();

            // Snapshot just these variations.
            $rows = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->leftJoin('variation_location_details as vld', function ($j) use ($location_id) {
                    $j->on('vld.variation_id', '=', 'v.id')
                      ->where('vld.location_id', $location_id);
                })
                ->whereIn('v.id', $variation_ids)
                ->where('p.business_id', $business_id)
                ->where('p.is_inactive', 0)
                ->where('p.not_for_selling', 0)
                ->select([
                    'v.id as variation_id',
                    'v.product_id',
                    DB::raw('COALESCE(vld.qty_available, 0) as theoretical'),
                    DB::raw('COALESCE(v.default_purchase_price, 0) as unit_cost'),
                ])
                ->get();

            $now = Carbon::now();
            $payload = $this->buildSessionLinesPayload($rows, $session->id, $business_id, $location_id, $request);
            foreach ($payload as &$p) {
                $p['created_at'] = $now;
                $p['updated_at'] = $now;
            }
            unset($p);

            if (! empty($payload)) {
                foreach (array_chunk($payload, 500) as $chunk) {
                    DB::table('inventory_count_session_lines')->insert($chunk);
                }
            }

            $session->lines_total = count($payload);
            $session->save();

            DB::commit();

            return response()->json([
                'success' => 1,
                'msg' => __('inventory_count.recount_session_created', [
                    'count' => count($payload),
                    'ref' => $reference_no,
                ]),
                'session_id' => $session->id,
                'redirect' => action([self::class, 'show'], ['id' => $session->id]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('InventoryCount@createRecountSession '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json(['success' => 0, 'msg' => trans('messages.something_went_wrong')], 500);
        }
    }

    /**
     * Supervisor dismisses one or more queue items as "false alarm" — no recount.
     */
    public function dismissRecountLines(Request $request)
    {
        if (! auth()->user()->can('inventory_count.approve')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'line_ids' => 'required|array|min:1',
            'resolution_notes' => 'required|string|max:1000',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');
        $line_ids = array_values(array_filter(array_map('intval', (array) $request->input('line_ids'))));

        $permitted_locations = auth()->user()->permitted_locations();

        $q = DB::table('inventory_count_session_lines as l')
            ->join('inventory_count_sessions as s', 's.id', '=', 'l.session_id')
            ->whereIn('l.id', $line_ids)
            ->where('s.business_id', $business_id)
            ->whereNotNull('l.flagged_at')
            ->whereNull('l.resolved_at');

        if ($permitted_locations !== 'all') {
            $q->whereIn('s.location_id', $permitted_locations);
        }

        $count = $q->update([
            'l.resolved_at' => Carbon::now(),
            'l.resolved_by' => $user_id,
            'l.resolution' => 'dismissed',
            'l.resolution_notes' => $request->input('resolution_notes'),
        ]);

        return response()->json([
            'success' => 1,
            'msg' => __('inventory_count.dismissed_count', ['count' => $count]),
            'count' => $count,
        ]);
    }

    /**
     * Validate that the given variation belongs to a line of this
     * inventory-count session AND that the product belongs to the
     * caller's business.  Returns the loaded Variation (with product
     * + product_tax eager loaded) or aborts.
     */
    private function loadSessionVariation($session_id, $variation_id, $business_id)
    {
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($session_id);

        $exists = InventoryCountSessionLine::where('session_id', $session->id)
            ->where('variation_id', $variation_id)
            ->exists();
        if (! $exists) {
            abort(404, 'Variation not part of this session');
        }

        $variation = Variation::with(['product', 'product.product_tax'])->findOrFail($variation_id);
        if ((int) $variation->product->business_id !== (int) $business_id) {
            abort(403);
        }

        return [$session, $variation];
    }

    /**
     * AJAX: quick-edit a variation's selling price (or a per-unit override)
     * from the inventory-count session screen.  Mirrors the exc/inc + tax
     * handling used by the product edit form so admins can correct prices
     * during the migration count without leaving the session.
     *
     * Params:
     *   variation_id        (int, required)
     *   price               (numeric string, required)
     *   price_includes_tax  (0/1) - whether `price` is inc-tax
     *   unit_id             (int, optional) - if provided AND not the base
     *                       unit of the product, a per-unit override row
     *                       is upserted (or deleted on blank); otherwise
     *                       the variation's base sell price is updated.
     */
    public function updateSellPrice(Request $request, $id)
    {
        if (! auth()->user()->can('product.update')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate([
            'variation_id' => 'required|integer',
            'price' => 'nullable|string',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $variation_id = (int) $request->input('variation_id');
        [$session, $variation] = $this->loadSessionVariation($id, $variation_id, $business_id);

        $product = $variation->product;
        $tax_pc = 0;
        if (! empty($product->tax) && $product->product_tax) {
            $tax_pc = (float) $product->product_tax->amount;
        }

        $price_raw = trim((string) $request->input('price', ''));
        $inc_tax = (bool) $request->input('price_includes_tax', 0);
        $unit_id = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        // Per-unit override path
        if ($unit_id && $unit_id !== (int) $product->unit_id) {
            // Ensure the unit is a valid sub-unit of the product
            $sub_unit_ids = [];
            if (! empty($product->sub_unit_ids)) {
                $sub_unit_ids = is_array($product->sub_unit_ids)
                    ? $product->sub_unit_ids
                    : json_decode($product->sub_unit_ids, true);
            }
            $allowed = array_map('intval', array_merge([(int) $product->unit_id], $sub_unit_ids ?: []));
            if (! in_array($unit_id, $allowed, true)) {
                return response()->json(['success' => 0, 'msg' => 'Invalid unit for this product'], 422);
            }

            if ($price_raw === '') {
                // blank = delete override
                VariationUnitPrice::where('variation_id', $variation_id)
                    ->where('unit_id', $unit_id)
                    ->delete();
                return response()->json([
                    'success' => 1,
                    'msg' => __('lang_v1.updated_succesfully'),
                    'unit_id' => $unit_id,
                    'price_exc_tax' => null,
                    'price_inc_tax' => null,
                ]);
            }

            $price = $this->productUtil->num_uf($price_raw);
            if ($inc_tax) {
                $price_inc = $price;
                $price_exc = $this->productUtil->calc_percentage_base($price, $tax_pc);
            } else {
                $price_exc = $price;
                $price_inc = $this->productUtil->calc_percentage($price, $tax_pc, $price);
            }

            VariationUnitPrice::updateOrCreate(
                ['variation_id' => $variation_id, 'unit_id' => $unit_id],
                ['price_exc_tax' => $price_exc, 'price_inc_tax' => $price_inc]
            );

            return response()->json([
                'success' => 1,
                'msg' => __('lang_v1.updated_succesfully'),
                'unit_id' => $unit_id,
                'price_exc_tax' => (float) $price_exc,
                'price_inc_tax' => (float) $price_inc,
            ]);
        }

        // Base price path
        if ($price_raw === '') {
            return response()->json(['success' => 0, 'msg' => 'Price required'], 422);
        }
        $price = $this->productUtil->num_uf($price_raw);
        if ($inc_tax) {
            $variation->sell_price_inc_tax = $price;
            $variation->default_sell_price = $this->productUtil->calc_percentage_base($price, $tax_pc);
        } else {
            $variation->default_sell_price = $price;
            $variation->sell_price_inc_tax = $this->productUtil->calc_percentage($price, $tax_pc, $price);
        }

        $dpp = (float) $variation->default_purchase_price;
        if ($dpp > 0) {
            $variation->profit_percent = (($variation->default_sell_price - $dpp) / $dpp) * 100;
        }
        $variation->save();

        return response()->json([
            'success' => 1,
            'msg' => __('lang_v1.updated_succesfully'),
            'variation_id' => $variation->id,
            'default_sell_price' => (float) $variation->default_sell_price,
            'sell_price_inc_tax' => (float) $variation->sell_price_inc_tax,
            'profit_percent' => (float) $variation->profit_percent,
        ]);
    }

    /**
     * AJAX: update the unit_cost on a single inventory-count session line.
     *
     * Scope of change is INTENTIONALLY narrow — this updates only the
     * session line's snapshotted unit_cost so that the variance valuation
     * on approval uses the corrected figure. It does NOT touch the
     * linked PurchaseLine.purchase_price, COGS on historical sales, or
     * variations.default_purchase_price (those would alter past reports).
     * Only allowed while the session is still in `draft`.
     */
    /**
     * AJAX: update a product's alert_quantity from the inventory count screen.
     * Mirrors the alert_quantity field on the product edit page; the value is
     * stored in the product's base unit (same as that page).
     */
    public function updateAlertQuantity(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create') || ! auth()->user()->can('product.update')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate([
            'product_id' => 'required|integer',
            'alert_quantity' => 'nullable|string',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.session_not_editable'),
            ], 422);
        }

        $product = \App\Product::where('business_id', $business_id)
            ->where('id', (int) $request->input('product_id'))
            ->first();
        if (! $product) {
            return response()->json(['success' => 0, 'msg' => 'Product not found'], 404);
        }

        $raw = trim((string) $request->input('alert_quantity', ''));
        if ($raw === '') {
            $product->alert_quantity = null;
        } else {
            $parsed = $this->productUtil->num_uf($raw);
            if (! is_numeric($parsed) || (float) $parsed < 0) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('validation.numeric', ['attribute' => __('product.alert_quantity')]),
                ], 422);
            }
            $product->alert_quantity = $parsed;
        }
        $product->save();

        return response()->json([
            'success' => 1,
            'msg' => __('lang_v1.updated_succesfully'),
            'product_id' => $product->id,
            'alert_quantity' => $product->alert_quantity !== null ? (float) $product->alert_quantity : null,
        ]);
    }

    public function updateUnitCost(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate([
            'line_id' => 'required|integer',
            'unit_cost' => 'required|numeric|min:0',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.session_not_editable'),
            ], 422);
        }

        $line = InventoryCountSessionLine::where('session_id', $session->id)
            ->where('id', (int) $request->input('line_id'))
            ->first();
        if (! $line) {
            return response()->json(['success' => 0, 'msg' => 'Line not found'], 404);
        }

        $new_cost = $this->productUtil->num_uf((string) $request->input('unit_cost'));
        $line->unit_cost = (float) $new_cost;
        $line->save();

        return response()->json([
            'success' => 1,
            'msg' => __('lang_v1.updated_succesfully'),
            'line_id' => $line->id,
            'unit_cost' => (float) $line->unit_cost,
        ]);
    }

    /**
     * AJAX: change a product's base unit from the inventory count screen.
     *
     * Hard guard: only allowed for products that have NO sub-units mapped.
     * For multi-unit products the user must go to the product edit page so
     * unit conversions, purchase prices per unit, and historical purchase
     * records stay consistent.
     *
     * Quantity columns (theoretical, counted, purchase qty, etc.) are not
     * touched — they are already stored in the product's base unit, so the
     * change is effectively a re-label of that base unit.
     */
    public function updateProductUnit(Request $request, $id)
    {
        if (! auth()->user()->can('inventory_count.create') || ! auth()->user()->can('product.update')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $request->validate([
            'product_id' => 'required|integer',
            'unit_id' => 'required|integer',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $session = InventoryCountSession::where('business_id', $business_id)->findOrFail($id);
        if ($session->status !== 'draft') {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.session_not_editable'),
            ], 422);
        }

        $product = \App\Product::where('business_id', $business_id)
            ->where('id', (int) $request->input('product_id'))
            ->first();
        if (! $product) {
            return response()->json(['success' => 0, 'msg' => 'Product not found'], 404);
        }

        // Block multi-unit products from being mutated here.
        $subs = [];
        if (! empty($product->sub_unit_ids)) {
            $decoded = is_array($product->sub_unit_ids)
                ? $product->sub_unit_ids
                : json_decode($product->sub_unit_ids, true);
            if (is_array($decoded)) {
                $subs = array_values(array_filter($decoded, fn($v) => (int) $v > 0));
            }
        }
        if (count($subs) > 0) {
            return response()->json([
                'success' => 0,
                'msg' => __('inventory_count.unit_edit_blocked_multi'),
            ], 422);
        }

        $unit = Unit::where('business_id', $business_id)
            ->where('id', (int) $request->input('unit_id'))
            ->first();
        if (! $unit) {
            return response()->json(['success' => 0, 'msg' => 'Unit not found'], 404);
        }

        $product->unit_id = $unit->id;
        $product->save();

        return response()->json([
            'success' => 1,
            'msg' => __('lang_v1.updated_succesfully'),
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_short_name' => $unit->short_name,
        ]);
    }

    /**
     * AJAX: return the per-unit price grid for a single variation so the
     * inventory-count screen can render an inline editor matching the
     * "Per-Unit Selling Prices" panel from the product edit form.
     *
     * Response:
     *   {
     *     success: 1,
     *     tax_percent: float,
     *     base_unit_id: int,
     *     units: [
     *       { unit_id, name, multiplier, is_base, price_exc_tax, price_inc_tax }
     *     ]
     *   }
     */
    public function unitPricesGrid(Request $request, $id)
    {
        if (! auth()->user()->can('product.update')) {
            return response()->json(['success' => 0, 'msg' => 'Forbidden'], 403);
        }

        $business_id = $request->session()->get('user.business_id');
        $variation_id = (int) $request->input('variation_id');
        [, $variation] = $this->loadSessionVariation($id, $variation_id, $business_id);

        $product = $variation->product;
        $tax_pc = 0;
        if (! empty($product->tax) && $product->product_tax) {
            $tax_pc = (float) $product->product_tax->amount;
        }

        $sub_unit_ids = [];
        if (! empty($product->sub_unit_ids)) {
            $sub_unit_ids = is_array($product->sub_unit_ids)
                ? $product->sub_unit_ids
                : json_decode($product->sub_unit_ids, true);
        }
        $unit_ids = array_unique(array_filter(array_map('intval', array_merge([(int) $product->unit_id], $sub_unit_ids ?: []))));

        $units = Unit::whereIn('id', $unit_ids)->get()->keyBy('id');
        $overrides = VariationUnitPrice::where('variation_id', $variation_id)->get()->keyBy('unit_id');

        $base = $units->get((int) $product->unit_id);
        $rows = [];
        foreach ($units as $u) {
            // Compute the unit's multiplier relative to the variation's base unit
            $multiplier = 1.0;
            if ((int) $u->id !== (int) $product->unit_id) {
                if ((int) $u->base_unit_id === (int) $product->unit_id) {
                    $multiplier = (float) $u->base_unit_multiplier;
                } elseif ($base && (int) $base->base_unit_id === (int) $u->id) {
                    // base unit is a sub of $u (rare)
                    $multiplier = $base->base_unit_multiplier > 0 ? 1 / (float) $base->base_unit_multiplier : 1.0;
                } else {
                    $multiplier = (float) ($u->base_unit_multiplier ?: 1);
                }
            }
            $ov = $overrides->get($u->id);
            $rows[] = [
                'unit_id' => (int) $u->id,
                'name' => $u->actual_name,
                'short_name' => $u->short_name,
                'multiplier' => (float) $multiplier,
                'is_base' => (int) $u->id === (int) $product->unit_id,
                'price_exc_tax' => $ov ? (float) $ov->price_exc_tax : null,
                'price_inc_tax' => $ov ? (float) $ov->price_inc_tax : null,
            ];
        }

        return response()->json([
            'success' => 1,
            'tax_percent' => $tax_pc,
            'base_unit_id' => (int) $product->unit_id,
            'variation_default_sell_price' => (float) $variation->default_sell_price,
            'variation_sell_price_inc_tax' => (float) $variation->sell_price_inc_tax,
            'units' => $rows,
        ]);
    }
}
