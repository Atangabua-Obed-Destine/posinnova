<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Department;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DepartmentIssueController extends Controller
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

    public function index()
    {
        if (! auth()->user()->can('department_issue.access') && ! auth()->user()->can('department_issue.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $edit_days = request()->session()->get('business.transaction_edit_days');

            $issues = Transaction::leftJoin('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
                ->leftJoin('departments as d', 'transactions.department_id', '=', 'd.id')
                ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'department_issue')
                ->select(
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
                ->addColumn('action', function ($row) use ($edit_days) {
                    $html = '<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent btn-modal" data-container=".view_modal" data-href="'.action([\App\Http\Controllers\DepartmentIssueController::class, 'show'], [$row->id]).'"><i class="fa fa-eye"></i> '.__('messages.view').'</button>';
                    $html .= ' <a href="'.action([\App\Http\Controllers\DepartmentIssueController::class, 'printVoucher'], [$row->id]).'" target="_blank" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info"><i class="fa fa-print"></i> '.__('messages.print').'</a>';

                    $date = \Carbon::parse($row->transaction_date)->addDays($edit_days);
                    $today = today();
                    if ($date->gte($today) && auth()->user()->can('department_issue.delete')) {
                        $html .= ' <button type="button" data-href="'.action([\App\Http\Controllers\DepartmentIssueController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_department_issue"><i class="fa fa-trash"></i> '.__('messages.delete').'</button>';
                    }
                    return $html;
                })
                ->rawColumns(['final_total', 'status', 'action'])
                ->make(true);
        }

        return view('department_issue.index');
    }

    public function create()
    {
        if (! auth()->user()->can('department_issue.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $departments = Department::forDropdown($business_id);

        if ($departments->isEmpty()) {
            return redirect()
                ->action([\App\Http\Controllers\DepartmentController::class, 'index'])
                ->with('status', [
                    'success' => 0,
                    'msg' => __('lang_v1.no_departments_create_first'),
                ]);
        }

        return view('department_issue.create')
            ->with(compact('business_locations', 'departments'));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('department_issue.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\DepartmentIssueController::class, 'index']));
            }

            $approval_required = (int) (request()->session()->get('business.dept_issue_approval_required', 0)) === 1;

            $request->validate([
                'location_id' => 'required|integer',
                'department_id' => 'required|integer',
                'transaction_date' => 'required',
                'products' => 'required|array|min:1',
            ]);

            // Validate department belongs to this business
            $dept_ok = Department::where('business_id', $business_id)
                ->where('id', $request->input('department_id'))
                ->where('is_active', 1)
                ->exists();
            if (! $dept_ok) {
                return redirect()->back()->with('status', ['success' => 0, 'msg' => __('lang_v1.invalid_department')]);
            }

            DB::beginTransaction();

            $user_id = $request->session()->get('user.id');
            $products = $request->input('products');

            $sell_lines = [];
            $final_total = 0;

            foreach ($products as $product) {
                $qty = $this->productUtil->num_uf($product['quantity']);
                $unit_price = $this->productUtil->num_uf($product['unit_price']);

                $sell_line = [
                    'product_id' => $product['product_id'],
                    'variation_id' => $product['variation_id'],
                    'quantity' => $qty,
                    'unit_price' => $unit_price,
                    'unit_price_inc_tax' => $unit_price,
                    'item_tax' => 0,
                    'line_total_tax' => 0,
                    'tax_id' => null,
                ];

                if (! empty($product['product_unit_id'])) {
                    $sell_line['product_unit_id'] = $product['product_unit_id'];
                }
                if (! empty($product['sub_unit_id'])) {
                    $sell_line['sub_unit_id'] = $product['sub_unit_id'];
                }
                if (! empty($product['base_unit_multiplier'])) {
                    $sell_line['base_unit_multiplier'] = $product['base_unit_multiplier'];
                }
                if (! empty($product['lot_no_line_id'])) {
                    $sell_line['lot_no_line_id'] = $product['lot_no_line_id'];
                }

                $sell_lines[] = $sell_line;
                $final_total += $qty * $unit_price;
            }

            // Generate reference number
            $ref_count = $this->productUtil->setAndGetReferenceCount('department_issue');
            $ref_no = $request->input('ref_no');
            if (empty($ref_no)) {
                $ref_no = $this->productUtil->generateReferenceNumber('department_issue', $ref_count, $business_id, 'DIS');
            }

            $status = $approval_required ? 'pending' : 'final';

            $transaction_data = [
                'business_id' => $business_id,
                'location_id' => $request->input('location_id'),
                'department_id' => $request->input('department_id'),
                'type' => 'department_issue',
                'status' => $status,
                'payment_status' => 'paid',
                'ref_no' => $ref_no,
                'transaction_date' => $this->productUtil->uf_date($request->input('transaction_date'), true),
                'total_before_tax' => $final_total,
                'final_total' => $final_total,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'discount_type' => 'fixed',
                'additional_notes' => $request->input('additional_notes'),
                'created_by' => $user_id,
            ];

            $issue = Transaction::create($transaction_data);

            // Reuse the TransactionUtil sell-lines creator to keep DB layout consistent
            $this->transactionUtil->createOrUpdateSellLines($issue, $sell_lines, $issue->location_id, false, null, [], false);

            // Decrement stock only if no approval required
            if ($status === 'final') {
                foreach ($products as $product) {
                    if (! empty($product['enable_stock'])) {
                        $decrease_qty = $this->productUtil->num_uf($product['quantity']);
                        if (! empty($product['base_unit_multiplier'])) {
                            $decrease_qty = $decrease_qty * $product['base_unit_multiplier'];
                        }
                        $this->productUtil->decreaseProductQuantity(
                            $product['product_id'],
                            $product['variation_id'],
                            $issue->location_id,
                            $decrease_qty
                        );
                    }
                }
            }

            DB::commit();

            $msg = $status === 'final'
                ? __('lang_v1.department_issue_added_success')
                : __('lang_v1.department_issue_added_pending');

            return redirect()
                ->action([\App\Http\Controllers\DepartmentIssueController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => $msg]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    public function show($id)
    {
        if (! auth()->user()->can('department_issue.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $issue = Transaction::with(['sell_lines', 'sell_lines.product', 'sell_lines.variations', 'sell_lines.sub_unit', 'location'])
            ->where('business_id', $business_id)
            ->where('type', 'department_issue')
            ->findOrFail($id);

        $department = Department::find($issue->department_id);
        $creator = \App\User::find($issue->created_by);

        return view('department_issue.show')->with(compact('issue', 'department', 'creator'));
    }

    public function printVoucher($id)
    {
        if (! auth()->user()->can('department_issue.access') && ! auth()->user()->can('department_issue.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $issue = Transaction::with(['sell_lines', 'sell_lines.product', 'sell_lines.variations', 'sell_lines.sub_unit', 'location'])
            ->where('business_id', $business_id)
            ->where('type', 'department_issue')
            ->findOrFail($id);

        $business = \App\Business::find($business_id);
        $department = Department::find($issue->department_id);
        $creator = \App\User::find($issue->created_by);

        return view('department_issue.print')->with(compact('issue', 'business', 'department', 'creator'));
    }

    public function destroy($id)
    {
        if (! auth()->user()->can('department_issue.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = request()->user()->business_id;

            DB::beginTransaction();

            $issue = Transaction::with('sell_lines')
                ->where('business_id', $business_id)
                ->where('type', 'department_issue')
                ->findOrFail($id);

            // Restore stock if it was finalized
            if ($issue->status === 'final') {
                foreach ($issue->sell_lines as $line) {
                    $qty = $line->quantity;
                    if (! empty($line->base_unit_multiplier)) {
                        $qty = $qty * $line->base_unit_multiplier;
                    }
                    $this->productUtil->updateProductQuantity(
                        $issue->location_id,
                        $line->product_id,
                        $line->variation_id,
                        $qty,
                        0,
                        null,
                        false
                    );
                }
            }

            $issue->sell_lines()->delete();
            $issue->delete();

            DB::commit();

            return [
                'success' => true,
                'msg' => __('lang_v1.department_issue_deleted_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }
}
