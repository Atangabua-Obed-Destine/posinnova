<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Department;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DepartmentIssueReportController extends Controller
{
    protected $productUtil;
    protected $transactionUtil;
    protected $moduleUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        //PosInnova: section switched off in Clinic settings
        $this->middleware(function ($request, $next) {
            if (! \App\Utils\ClinicSettings::sectionOn($request->session()->get('user.business_id'), 'departments')) {
                abort(404);
            }

            return $next($request);
        });
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    private function authorizeReport()
    {
        if (! auth()->user()->can('department_issue.view_report')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Apply standard filters to a Transaction query (location, department, status, date).
     */
    private function applyFilters($query, Request $request, $business_id, $alias = 'transactions')
    {
        $query->where($alias . '.business_id', $business_id)
              ->where($alias . '.type', 'department_issue');

        $permitted = auth()->user()->permitted_locations();
        if ($permitted != 'all') {
            $query->whereIn($alias . '.location_id', $permitted);
        }

        if ($request->filled('location_id')) {
            $query->where($alias . '.location_id', $request->input('location_id'));
        }
        if ($request->filled('department_id')) {
            $query->where($alias . '.department_id', $request->input('department_id'));
        }
        if ($request->filled('status')) {
            $query->where($alias . '.status', $request->input('status'));
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween(
                DB::raw('date(' . $alias . '.transaction_date)'),
                [$request->input('start_date'), $request->input('end_date')]
            );
        }
    }

    /**
     * Main report page — renders filter form, summary cards, and tabbed tables.
     */
    public function index(Request $request)
    {
        $this->authorizeReport();

        $business_id = $request->session()->get('user.business_id');

        if ($request->input('summary') == 1) {
            $query = Transaction::query();
            $this->applyFilters($query, $request, $business_id);

            $summary = $query->select(
                DB::raw('COUNT(transactions.id) as total_issues'),
                DB::raw('SUM(transactions.final_total) as total_amount'),
                DB::raw("SUM(IF(transactions.status='final', transactions.final_total, 0)) as total_issued"),
                DB::raw("SUM(IF(transactions.status='pending', transactions.final_total, 0)) as total_pending")
            )->first();

            // Distinct department count
            $dept_count = Transaction::where('transactions.business_id', $business_id)
                ->where('transactions.type', 'department_issue')
                ->when($request->filled('location_id'), function ($q) use ($request) {
                    return $q->where('transactions.location_id', $request->input('location_id'));
                })
                ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                    return $q->whereBetween(DB::raw('date(transactions.transaction_date)'), [$request->input('start_date'), $request->input('end_date')]);
                })
                ->distinct('transactions.department_id')
                ->count('transactions.department_id');

            return response()->json([
                'total_issues' => (int) ($summary->total_issues ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
                'total_issued' => (float) ($summary->total_issued ?? 0),
                'total_pending' => (float) ($summary->total_pending ?? 0),
                'department_count' => $dept_count,
            ]);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);
        $departments = Department::forBusiness($business_id)
                        ->orderBy('name')
                        ->pluck('name', 'id');

        return view('report.department_issue_report')
                ->with(compact('business_locations', 'departments'));
    }

    /**
     * Voucher-level DataTable (one row per issue).
     */
    public function details(Request $request)
    {
        $this->authorizeReport();

        if (! $request->ajax()) {
            abort(404);
        }

        $business_id = $request->session()->get('user.business_id');

        $query = Transaction::leftJoin('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
                    ->leftJoin('departments as d', 'transactions.department_id', '=', 'd.id')
                    ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id');

        $this->applyFilters($query, $request, $business_id);

        $issues = $query->select(
            'transactions.id',
            'transactions.transaction_date',
            'transactions.ref_no',
            'transactions.final_total',
            'transactions.additional_notes',
            'transactions.status',
            'bl.name as location_name',
            'd.name as department_name',
            DB::raw("CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as issued_by")
        );

        return Datatables::of($issues)
            ->editColumn('transaction_date', function ($row) {
                return \Carbon::parse($row->transaction_date)->format(session('business.date_format', 'd/m/Y') . ' H:i');
            })
            ->editColumn('final_total', function ($row) {
                return '<span class="display_currency" data-currency_symbol="true">'.$row->final_total.'</span>';
            })
            ->editColumn('status', function ($row) {
                if ($row->status == 'final') {
                    return '<span class="label label-success">'.__('lang_v1.issued').'</span>';
                } elseif ($row->status == 'pending') {
                    return '<span class="label label-warning">'.__('lang_v1.pending').'</span>';
                }
                return ucfirst($row->status);
            })
            ->addColumn('action', function ($row) {
                return '<a href="'.action([\App\Http\Controllers\DepartmentIssueController::class, 'printVoucher'], [$row->id]).'" target="_blank" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info"><i class="fa fa-print"></i> '.__('messages.print').'</a>';
            })
            ->rawColumns(['final_total', 'status', 'action'])
            ->make(true);
    }

    /**
     * Aggregated by department.
     */
    public function byDepartment(Request $request)
    {
        $this->authorizeReport();

        if (! $request->ajax()) {
            abort(404);
        }

        $business_id = $request->session()->get('user.business_id');

        $query = Transaction::join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 'transactions.id')
                    ->leftJoin('units as sub_un', 'tsl.sub_unit_id', '=', 'sub_un.id')
                    ->leftJoin('departments as d', 'transactions.department_id', '=', 'd.id');

        $this->applyFilters($query, $request, $business_id);

        $data = $query->select(
                    'd.id as department_id',
                    DB::raw("COALESCE(d.name, '-') as department_name"),
                    DB::raw("COALESCE(d.hod_name, '') as hod_name"),
                    DB::raw('COUNT(DISTINCT transactions.id) as total_issues'),
                    DB::raw('SUM(tsl.quantity * IFNULL(sub_un.base_unit_multiplier, 1)) as total_quantity'),
                    DB::raw('SUM(tsl.quantity * tsl.unit_price) as total_amount')
                )
                ->groupBy('d.id', 'd.name', 'd.hod_name')
                ->orderByDesc('total_amount');

        return Datatables::of($data)
            ->editColumn('total_quantity', function ($row) {
                return '<span>'.$this->productUtil->num_f($row->total_quantity).'</span>';
            })
            ->editColumn('total_amount', function ($row) {
                return '<span class="display_currency" data-currency_symbol="true">'.$row->total_amount.'</span>';
            })
            ->rawColumns(['total_quantity', 'total_amount'])
            ->make(true);
    }

    /**
     * Aggregated by product (variation).
     */
    public function byProduct(Request $request)
    {
        $this->authorizeReport();

        if (! $request->ajax()) {
            abort(404);
        }

        $business_id = $request->session()->get('user.business_id');

        $query = Transaction::join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 'transactions.id')
                    ->join('products as p', 'p.id', '=', 'tsl.product_id')
                    ->join('variations as v', 'v.id', '=', 'tsl.variation_id')
                    ->leftJoin('units as un', 'un.id', '=', 'p.unit_id')
                    ->leftJoin('units as sub_un', 'tsl.sub_unit_id', '=', 'sub_un.id');

        $this->applyFilters($query, $request, $business_id);

        if ($request->filled('product_id')) {
            $query->where('p.id', $request->input('product_id'));
        }

        $data = $query->select(
                    'p.id as product_id',
                    'p.name as product_name',
                    'v.sub_sku',
                    DB::raw("COALESCE(un.short_name, '') as unit"),
                    DB::raw('COUNT(DISTINCT transactions.id) as issue_count'),
                    DB::raw('SUM(tsl.quantity * IFNULL(sub_un.base_unit_multiplier, 1)) as total_quantity'),
                    DB::raw('SUM(tsl.quantity * tsl.unit_price) as total_amount')
                )
                ->groupBy('p.id', 'p.name', 'v.sub_sku', 'un.short_name')
                ->orderByDesc('total_amount');

        return Datatables::of($data)
            ->editColumn('total_quantity', function ($row) {
                return '<span>'.$this->productUtil->num_f($row->total_quantity).' '.$row->unit.'</span>';
            })
            ->editColumn('total_amount', function ($row) {
                return '<span class="display_currency" data-currency_symbol="true">'.$row->total_amount.'</span>';
            })
            ->rawColumns(['total_quantity', 'total_amount'])
            ->make(true);
    }

    /**
     * Aggregated by period (default = month).
     */
    public function byPeriod(Request $request)
    {
        $this->authorizeReport();

        if (! $request->ajax()) {
            abort(404);
        }

        $business_id = $request->session()->get('user.business_id');
        $group = $request->input('group_by', 'month'); // day|month|year

        if ($group === 'day') {
            $period_sql = "DATE(transactions.transaction_date)";
        } elseif ($group === 'year') {
            $period_sql = "DATE_FORMAT(transactions.transaction_date, '%Y')";
        } else {
            $period_sql = "DATE_FORMAT(transactions.transaction_date, '%Y-%m')";
        }

        $query = Transaction::query();
        $this->applyFilters($query, $request, $business_id);

        $data = $query->select(
                    DB::raw($period_sql . ' as period'),
                    DB::raw('COUNT(transactions.id) as total_issues'),
                    DB::raw('SUM(transactions.final_total) as total_amount')
                )
                ->groupBy(DB::raw($period_sql))
                ->orderBy(DB::raw($period_sql), 'desc');

        return Datatables::of($data)
            ->editColumn('total_amount', function ($row) {
                return '<span class="display_currency" data-currency_symbol="true">'.$row->total_amount.'</span>';
            })
            ->rawColumns(['total_amount'])
            ->make(true);
    }
}
