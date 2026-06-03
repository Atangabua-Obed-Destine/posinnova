<?php

namespace Modules\Accounting\Http\Controllers;

use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Utils\AccountingUtil;

class ReportController extends Controller
{
    protected $accountingUtil;

    protected $businessUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(AccountingUtil $accountingUtil, BusinessUtil $businessUtil,
    ModuleUtil $moduleUtil)
    {
        $this->accountingUtil = $accountingUtil;
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $first_account = AccountingAccount::where('business_id', $business_id)
                            ->where('status', 'active')
                            ->first();
        $ledger_url = null;
        if (! empty($first_account)) {
            $ledger_url = route('accounting.ledger', $first_account);
        }

        return view('accounting::report.index')
            ->with(compact('ledger_url'));
    }

    /**
     * Trial Balance
     *
     * @return Response
     */
    public function trialBalance()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            $start_date = request()->start_date;
            $end_date = request()->end_date;
        } else {
            $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
            $start_date = $fy['start'];
            $end_date = $fy['end'];
        }

        $location_id = request()->get('location_id');
        if ($location_id === '') {
            $location_id = null;
        }
        $format = request()->get('format', 'html'); // html | csv | pdf

        // Aggregated balance lines per account: opening, period dr/cr, closing.
        // Single query covering every account in the business (LEFT JOIN keeps
        // accounts with no activity visible, since the user wants all rows shown).
        $debit_natured_sql = "(accounting_accounts.account_primary_type IN ('asset','expense') AND AAT.type='debit')"
            ." OR (accounting_accounts.account_primary_type IN ('income','equity','liability') AND AAT.type='credit')";

        $opening_expr = "SUM(CASE WHEN AAT.id IS NOT NULL AND DATE(AAT.operation_date) < ? THEN
            (CASE WHEN ($debit_natured_sql) THEN AAT.amount ELSE -1 * AAT.amount END)
            ELSE 0 END)";

        $period_debit_expr = 'SUM(CASE WHEN AAT.id IS NOT NULL '
            .'AND DATE(AAT.operation_date) BETWEEN ? AND ? '
            ."AND AAT.type='debit' THEN AAT.amount ELSE 0 END)";

        $period_credit_expr = 'SUM(CASE WHEN AAT.id IS NOT NULL '
            .'AND DATE(AAT.operation_date) BETWEEN ? AND ? '
            ."AND AAT.type='credit' THEN AAT.amount ELSE 0 END)";

        $closing_expr = "SUM(CASE WHEN AAT.id IS NOT NULL AND DATE(AAT.operation_date) <= ? THEN
            (CASE WHEN ($debit_natured_sql) THEN AAT.amount ELSE -1 * AAT.amount END)
            ELSE 0 END)";

        $bindings = [
            $start_date,                          // opening
            $start_date, $end_date,               // period debit
            $start_date, $end_date,               // period credit
            $end_date,                            // closing
        ];

        $rows = AccountingAccount::query()
            ->leftJoin('accounting_accounts_transactions as AAT', function ($join) use ($location_id) {
                $join->on('AAT.accounting_account_id', '=', 'accounting_accounts.id');
                if (! empty($location_id)) {
                    // When a location filter is active, only AAT rows whose
                    // underlying transaction is in that location count. Manual
                    // journal entries / transfers (no transaction_id) are kept.
                    $join->whereRaw('(AAT.transaction_id IS NULL OR EXISTS '
                        .'(SELECT 1 FROM transactions t WHERE t.id = AAT.transaction_id AND t.location_id = ?))', [$location_id]);
                }
            })
            ->leftJoin('accounting_account_types as AATP', 'AATP.id', '=', 'accounting_accounts.account_sub_type_id')
            ->where('accounting_accounts.business_id', $business_id)
            ->select(
                'accounting_accounts.id',
                'accounting_accounts.gl_code',
                'accounting_accounts.name',
                'accounting_accounts.account_primary_type',
                'accounting_accounts.status',
                'AATP.name as sub_type'
            )
            ->selectRaw("$opening_expr as opening_balance", [$start_date])
            ->selectRaw("$period_debit_expr as period_debit", [$start_date, $end_date])
            ->selectRaw("$period_credit_expr as period_credit", [$start_date, $end_date])
            ->selectRaw("$closing_expr as closing_balance", [$end_date])
            ->groupBy(
                'accounting_accounts.id',
                'accounting_accounts.gl_code',
                'accounting_accounts.name',
                'accounting_accounts.account_primary_type',
                'accounting_accounts.status',
                'AATP.name'
            )
            ->orderBy('accounting_accounts.account_primary_type')
            ->orderBy('AATP.name')
            ->orderByRaw('COALESCE(accounting_accounts.gl_code, accounting_accounts.name)')
            ->get();

        // Group rows by primary type for subtotals.
        // Use a fixed display order for accounting readability.
        $primary_order = ['asset' => 1, 'liability' => 2, 'equity' => 3, 'income' => 4, 'expense' => 5];
        $primary_labels = [
            'asset' => __('accounting::lang.asset'),
            'liability' => __('accounting::lang.liability'),
            'equity' => __('accounting::lang.equity'),
            'income' => __('accounting::lang.income'),
            'expense' => __('accounting::lang.expenses'),
        ];

        $groups = [];
        foreach ($rows as $r) {
            $pt = $r->account_primary_type ?: 'asset';
            // Split signed natural balances into debit / credit columns.
            $opening = (float) $r->opening_balance;
            $closing = (float) $r->closing_balance;

            $is_debit_natured = in_array($pt, ['asset', 'expense']);

            $r->opening_debit  = ($is_debit_natured ? max($opening, 0) : max(-1 * $opening, 0));
            $r->opening_credit = ($is_debit_natured ? max(-1 * $opening, 0) : max($opening, 0));
            $r->closing_debit  = ($is_debit_natured ? max($closing, 0) : max(-1 * $closing, 0));
            $r->closing_credit = ($is_debit_natured ? max(-1 * $closing, 0) : max($closing, 0));

            $groups[$pt]['rows'][] = $r;
        }

        // Compute group + grand totals and ensure deterministic ordering.
        $totals = [
            'opening_debit' => 0, 'opening_credit' => 0,
            'period_debit' => 0,  'period_credit' => 0,
            'closing_debit' => 0, 'closing_credit' => 0,
        ];
        $ordered_groups = [];
        foreach ($primary_order as $pt => $_idx) {
            if (! isset($groups[$pt])) {
                continue;
            }
            $sub = ['opening_debit' => 0, 'opening_credit' => 0,
                'period_debit' => 0, 'period_credit' => 0,
                'closing_debit' => 0, 'closing_credit' => 0];
            foreach ($groups[$pt]['rows'] as $r) {
                $sub['opening_debit']  += $r->opening_debit;
                $sub['opening_credit'] += $r->opening_credit;
                $sub['period_debit']   += (float) $r->period_debit;
                $sub['period_credit']  += (float) $r->period_credit;
                $sub['closing_debit']  += $r->closing_debit;
                $sub['closing_credit'] += $r->closing_credit;
            }
            foreach ($sub as $k => $v) {
                $totals[$k] += $v;
            }
            $ordered_groups[$pt] = [
                'label' => $primary_labels[$pt] ?? ucfirst($pt),
                'rows'  => $groups[$pt]['rows'],
                'subtotals' => $sub,
            ];
        }

        $is_balanced = round($totals['closing_debit'], 2) === round($totals['closing_credit'], 2);
        $imbalance = $totals['closing_debit'] - $totals['closing_credit'];

        $business_locations = BusinessLocation::forDropdown($business_id, false);

        if ($format === 'csv') {
            return $this->trialBalanceCsv($ordered_groups, $totals, $is_balanced, $imbalance, $start_date, $end_date);
        }

        if ($format === 'pdf') {
            $pdf = \PDF::loadView('accounting::report.trial_balance_pdf', compact(
                'ordered_groups', 'totals', 'is_balanced', 'imbalance', 'start_date', 'end_date'
            ))->setPaper('a4', 'landscape');

            return $pdf->stream('trial_balance_'.$start_date.'_'.$end_date.'.pdf');
        }

        return view('accounting::report.trial_balance')
            ->with(compact(
                'ordered_groups', 'totals', 'is_balanced', 'imbalance',
                'start_date', 'end_date', 'business_locations', 'location_id'
            ));
    }

    /**
     * Trial Balance CSV export.
     */
    protected function trialBalanceCsv($ordered_groups, $totals, $is_balanced, $imbalance, $start_date, $end_date)
    {
        $filename = 'trial_balance_'.$start_date.'_'.$end_date.'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($ordered_groups, $totals, $is_balanced, $imbalance, $start_date, $end_date) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Trial Balance', $start_date.' ~ '.$end_date]);
            fputcsv($out, []);
            fputcsv($out, ['Code', 'Account', 'Sub Type',
                'Opening Debit', 'Opening Credit',
                'Period Debit', 'Period Credit',
                'Closing Debit', 'Closing Credit']);

            foreach ($ordered_groups as $pt => $g) {
                fputcsv($out, [strtoupper($g['label'])]);
                foreach ($g['rows'] as $r) {
                    fputcsv($out, [
                        $r->gl_code, $r->name, $r->sub_type,
                        number_format($r->opening_debit, 2, '.', ''),
                        number_format($r->opening_credit, 2, '.', ''),
                        number_format((float) $r->period_debit, 2, '.', ''),
                        number_format((float) $r->period_credit, 2, '.', ''),
                        number_format($r->closing_debit, 2, '.', ''),
                        number_format($r->closing_credit, 2, '.', ''),
                    ]);
                }
                $st = $g['subtotals'];
                fputcsv($out, ['', 'Subtotal '.$g['label'], '',
                    number_format($st['opening_debit'], 2, '.', ''),
                    number_format($st['opening_credit'], 2, '.', ''),
                    number_format($st['period_debit'], 2, '.', ''),
                    number_format($st['period_credit'], 2, '.', ''),
                    number_format($st['closing_debit'], 2, '.', ''),
                    number_format($st['closing_credit'], 2, '.', ''),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['', 'GRAND TOTAL', '',
                number_format($totals['opening_debit'], 2, '.', ''),
                number_format($totals['opening_credit'], 2, '.', ''),
                number_format($totals['period_debit'], 2, '.', ''),
                number_format($totals['period_credit'], 2, '.', ''),
                number_format($totals['closing_debit'], 2, '.', ''),
                number_format($totals['closing_credit'], 2, '.', ''),
            ]);
            fputcsv($out, ['', $is_balanced ? 'Status: BALANCED' : 'Status: OUT OF BALANCE',
                '', '', '', '', '', '', number_format($imbalance, 2, '.', '')]);

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Balance Sheet — as-of-date cumulative report.
     *
     * Shows assets, liabilities and equity at a single point in time. Includes
     * net income for the current financial year as a synthetic equity line so
     * the sheet actually balances while the books are still open.
     *
     * @return Response
     */
    public function balanceSheet()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        // Single "as of" date. Falls back to financial-year end, then to today.
        $as_of_date = request()->get('as_of_date');
        if (empty($as_of_date)) {
            // Backwards-compat: if old links pass end_date, honour it.
            $as_of_date = request()->get('end_date');
        }
        if (empty($as_of_date)) {
            $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
            $as_of_date = $fy['end'] ?? now()->toDateString();
        }

        $location_id = request()->get('location_id');
        if ($location_id === '') {
            $location_id = null;
        }
        $format = request()->get('format', 'html');

        // Cumulative natural balance up to and including $as_of_date.
        $debit_natured_sql = "(accounting_accounts.account_primary_type IN ('asset','expense') AND AAT.type='debit')"
            ." OR (accounting_accounts.account_primary_type IN ('income','equity','liability') AND AAT.type='credit')";
        $balance_expr = "SUM(CASE WHEN AAT.id IS NOT NULL AND DATE(AAT.operation_date) <= ? THEN
            (CASE WHEN ($debit_natured_sql) THEN AAT.amount ELSE -1 * AAT.amount END)
            ELSE 0 END)";

        $rows_query = AccountingAccount::query()
            ->leftJoin('accounting_accounts_transactions as AAT', function ($join) use ($location_id) {
                $join->on('AAT.accounting_account_id', '=', 'accounting_accounts.id');
                if (! empty($location_id)) {
                    $join->whereRaw('(AAT.transaction_id IS NULL OR EXISTS '
                        .'(SELECT 1 FROM transactions t WHERE t.id = AAT.transaction_id AND t.location_id = ?))', [$location_id]);
                }
            })
            ->leftJoin('accounting_account_types as AATP', 'AATP.id', '=', 'accounting_accounts.account_sub_type_id')
            ->where('accounting_accounts.business_id', $business_id)
            ->select(
                'accounting_accounts.id',
                'accounting_accounts.gl_code',
                'accounting_accounts.name',
                'accounting_accounts.account_primary_type',
                'accounting_accounts.status',
                'AATP.name as sub_type'
            )
            ->selectRaw("$balance_expr as balance", [$as_of_date])
            ->groupBy(
                'accounting_accounts.id',
                'accounting_accounts.gl_code',
                'accounting_accounts.name',
                'accounting_accounts.account_primary_type',
                'accounting_accounts.status',
                'AATP.name'
            )
            ->orderBy('AATP.name')
            ->orderByRaw('COALESCE(accounting_accounts.gl_code, accounting_accounts.name)');

        $rows = $rows_query->get();

        $bs = [
            'asset'     => ['label' => __('accounting::lang.asset'),     'subgroups' => [], 'total' => 0.0],
            'liability' => ['label' => __('accounting::lang.liability'), 'subgroups' => [], 'total' => 0.0],
            'equity'    => ['label' => __('accounting::lang.equity'),    'subgroups' => [], 'total' => 0.0],
        ];

        // Income & Expense are *not* shown on the sheet directly, but their net
        // becomes the "Net income (current period)" equity line.
        $net_income = 0.0;

        foreach ($rows as $r) {
            $bal = (float) $r->balance;
            $pt = $r->account_primary_type;

            if ($pt === 'income') {
                // income natural side is credit (positive) → adds to equity
                $net_income += $bal;
                continue;
            }
            if ($pt === 'expense') {
                // expense natural side is debit (positive) → reduces equity
                $net_income -= $bal;
                continue;
            }
            if (! isset($bs[$pt])) {
                continue;
            }
            $sub = $r->sub_type ?: __('accounting::lang.uncategorized', [], 'Uncategorized');
            if (! isset($bs[$pt]['subgroups'][$sub])) {
                $bs[$pt]['subgroups'][$sub] = ['rows' => [], 'subtotal' => 0.0];
            }
            $bs[$pt]['subgroups'][$sub]['rows'][] = $r;
            $bs[$pt]['subgroups'][$sub]['subtotal'] += $bal;
            $bs[$pt]['total'] += $bal;
        }

        // Synthetic "Net income (current period)" line under Equity.
        if (abs($net_income) > 0.005) {
            $sub_label = __('accounting::lang.retained_earnings', [], 'Retained Earnings');
            $synthetic = (object) [
                'id'         => null,
                'gl_code'    => null,
                'name'       => __('accounting::lang.net_income_current_period', [], 'Net income (current period)'),
                'sub_type'   => $sub_label,
                'status'     => 'active',
                'balance'    => $net_income,
                '__synthetic'=> true,
            ];
            if (! isset($bs['equity']['subgroups'][$sub_label])) {
                $bs['equity']['subgroups'][$sub_label] = ['rows' => [], 'subtotal' => 0.0];
            }
            $bs['equity']['subgroups'][$sub_label]['rows'][] = $synthetic;
            $bs['equity']['subgroups'][$sub_label]['subtotal'] += $net_income;
            $bs['equity']['total'] += $net_income;
        }

        $total_assets = $bs['asset']['total'];
        $total_liab_owners = $bs['liability']['total'] + $bs['equity']['total'];
        $is_balanced = round($total_assets, 2) === round($total_liab_owners, 2);
        $imbalance = $total_assets - $total_liab_owners;

        $business_locations = BusinessLocation::forDropdown($business_id, false);

        if ($format === 'csv') {
            return $this->balanceSheetCsv($bs, $total_assets, $total_liab_owners, $is_balanced, $imbalance, $as_of_date);
        }

        if ($format === 'pdf') {
            $pdf = \PDF::loadView('accounting::report.balance_sheet_pdf', compact(
                'bs', 'total_assets', 'total_liab_owners', 'is_balanced', 'imbalance', 'as_of_date', 'net_income'
            ))->setPaper('a4', 'portrait');

            return $pdf->stream('balance_sheet_'.$as_of_date.'.pdf');
        }

        return view('accounting::report.balance_sheet')
            ->with(compact(
                'bs', 'total_assets', 'total_liab_owners', 'is_balanced', 'imbalance',
                'as_of_date', 'net_income', 'business_locations', 'location_id'
            ));
    }

    /**
     * Balance Sheet CSV export.
     */
    protected function balanceSheetCsv($bs, $total_assets, $total_liab_owners, $is_balanced, $imbalance, $as_of_date)
    {
        $filename = 'balance_sheet_'.$as_of_date.'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($bs, $total_assets, $total_liab_owners, $is_balanced, $imbalance, $as_of_date) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Balance Sheet', 'As of '.$as_of_date]);
            fputcsv($out, []);
            fputcsv($out, ['Code', 'Account', 'Sub Type', 'Amount']);

            foreach (['asset', 'liability', 'equity'] as $pt) {
                fputcsv($out, [strtoupper($bs[$pt]['label'])]);
                foreach ($bs[$pt]['subgroups'] as $sub_name => $g) {
                    fputcsv($out, ['', $sub_name]);
                    foreach ($g['rows'] as $r) {
                        fputcsv($out, [
                            $r->gl_code, $r->name, $r->sub_type,
                            number_format((float) $r->balance, 2, '.', ''),
                        ]);
                    }
                    fputcsv($out, ['', 'Subtotal '.$sub_name, '',
                        number_format($g['subtotal'], 2, '.', '')]);
                }
                fputcsv($out, ['', 'TOTAL '.strtoupper($bs[$pt]['label']), '',
                    number_format($bs[$pt]['total'], 2, '.', '')]);
                fputcsv($out, []);
            }

            fputcsv($out, ['', 'TOTAL ASSETS', '', number_format($total_assets, 2, '.', '')]);
            fputcsv($out, ['', 'TOTAL LIABILITIES + EQUITY', '', number_format($total_liab_owners, 2, '.', '')]);
            fputcsv($out, ['', $is_balanced ? 'Status: BALANCED' : 'Status: OUT OF BALANCE', '',
                number_format($imbalance, 2, '.', '')]);

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function accountReceivableAgeingReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'sell', 'contact', $location_id);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_receivable_ageing_report')
        ->with(compact('report_details', 'business_locations'));
    }

    public function accountPayableAgeingReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);
        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'purchase', 'contact',
        $location_id);
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_payable_ageing_report')
        ->with(compact('report_details', 'business_locations'));
    }

    public function accountReceivableAgeingDetails()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'sell', 'due_date',
        $location_id);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_receivable_ageing_details')
        ->with(compact('business_locations', 'report_details'));
    }

    public function accountPayableAgeingDetails()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'purchase', 'due_date',
        $location_id);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_payable_ageing_details')
        ->with(compact('business_locations', 'report_details'));
    }
}
