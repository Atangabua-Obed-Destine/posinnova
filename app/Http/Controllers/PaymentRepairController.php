<?php

namespace App\Http\Controllers;

use App\Utils\ChangePaymentRepair;
use App\Utils\TransactionUtil;
use App\Utils\Util;

/**
 * Settings > Repair payments: lists the sales of this business whose change was
 * subtracted twice (shown as "partial" although fully paid) and repairs them with
 * one click. Business Admins (and the superadmin) only.
 */
class PaymentRepairController extends Controller
{
    protected $commonUtil;

    protected $transactionUtil;

    public function __construct(Util $commonUtil, TransactionUtil $transactionUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->transactionUtil = $transactionUtil;
    }

    private function authorizeAdmin()
    {
        $user = auth()->user();
        if (! ($user->can('superadmin') || $this->commonUtil->is_admin($user, request()->session()->get('user.business_id')))) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index()
    {
        $this->authorizeAdmin();
        $rows = ChangePaymentRepair::find(request()->session()->get('user.business_id'));

        return view('payment_repair.index', compact('rows'));
    }

    public function repair()
    {
        $this->authorizeAdmin();
        $rows = ChangePaymentRepair::find(request()->session()->get('user.business_id'));
        $fixed = ChangePaymentRepair::repair($rows, $this->transactionUtil, auth()->user()->username ?? auth()->id());

        return redirect()->action([self::class, 'index'])->with('status', [
            'success' => 1,
            'msg' => $fixed ? trans_choice('lang_v1.payment_repair_done', $fixed, ['n' => $fixed]) : __('lang_v1.payment_repair_none'),
        ]);
    }
}
