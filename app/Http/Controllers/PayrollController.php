<?php

namespace App\Http\Controllers;

use App\Payroll\Payroll;
use App\Payroll\PayrollBook;
use App\Payroll\PayrollCalculator;
use App\Payroll\PayrollException;
use App\Payroll\PayrollBankFile;
use App\Payroll\PayrollLineType;
use App\Payroll\PayrollLoan;
use App\Payroll\PayrollLoans;
use App\Payroll\PayrollRemittance;
use App\Payroll\PayrollRemittances;
use App\Payroll\PayrollRules;
use App\Payroll\PayrollStaff;
use App\Payroll\PayrollTaxGroup;
use App\Payroll\PayrollTaxSetting;
use App\User;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll (paxhitestbed's Payroll screens and wording): the month list (generate, approve,
 * pay ticked), a staff member's payroll (allowances, deductions, the calculation), paying and
 * reversing through the review, the payslip, the report, each staff member's pay details, and the
 * settings (approval, part months, allowance and deduction types, the payroll taxes, the bank file);
 * staff loans and advances, paying the withheld taxes over (remittances), the bank file.
 */
class PayrollController extends Controller
{
    protected function biz()
    {
        return (int) request()->session()->get('user.business_id');
    }

    protected function guard(...$perms)
    {
        if (! PayrollRules::enabled($this->biz())) {
            abort(404);
        }
        foreach ($perms as $p) {
            if (auth()->user()->can($p)) {
                return;
            }
        }
        abort(403, __('messages.unauthorized_action'));
    }

    public static function can($perm)
    {
        return auth()->check() && auth()->user()->can($perm);
    }

    private function back($ok, $msg, $to = null)
    {
        return ($to ?: redirect()->back())->with('status', ['success' => $ok ? 1 : 0, 'msg' => $msg]);
    }

    private function payroll($id)
    {
        return Payroll::where('business_id', $this->biz())->with(['user', 'details', 'taxLines'])->findOrFail($id);
    }

    private function month(Request $request)
    {
        $m = max(1, min(12, (int) $request->input('month', now()->month)));
        $y = max(2000, min(2100, (int) $request->input('year', now()->year)));

        return Carbon::create($y, $m, 1)->startOfDay();
    }

    private function accounts()
    {
        return DB::table('accounts')->where('business_id', $this->biz())->where('is_closed', 0)->whereNull('deleted_at')->orderBy('name')->pluck('name', 'id');
    }

    // ---------------------------------------------------------------- the month

    public function index(Request $request)
    {
        $this->guard('payroll-view', 'payroll-action');
        $bid = $this->biz();
        $month = $this->month($request);
        $staff = PayrollStaff::where('business_id', $bid)->where('is_active', 1)->with('user')->get()
            ->filter(fn ($s) => $s->user && ! $s->user->deleted_at)->sortBy(fn ($s) => mb_strtolower($s->user->first_name.' '.$s->user->last_name))->values();
        $payrolls = Payroll::where('business_id', $bid)->where('salary_month', $month->toDateString())->get()->keyBy('user_id');

        return view('payroll.index', [
            'month' => $month, 'staff' => $staff, 'payrolls' => $payrolls, 's' => PayrollRules::settings($bid),
            'accounts' => $this->accounts(), 'hasTaxes' => PayrollRules::hasTaxes($bid),
            'totals' => ['net' => $payrolls->sum('net_salary'), 'cost' => $payrolls->sum('total_cost'), 'paid' => $payrolls->where('status', 1)->count()],
        ]);
    }

    public function generate($user_id, $month, $year)
    {
        $this->guard('payroll-action');
        $bid = $this->biz();
        $m = Carbon::create((int) $year, max(1, min(12, (int) $month)), 1)->startOfDay();
        $staff = PayrollStaff::where('business_id', $bid)->where('user_id', $user_id)->with('user')->firstOrFail();
        $p = Payroll::where('business_id', $bid)->where('user_id', $user_id)->where('salary_month', $m->toDateString())->with('details')->first();
        if ($p && $p->isPaid()) {
            return $this->back(false, __('payroll.err_paid_locked'), redirect()->action([self::class, 'index'], ['month' => $m->month, 'year' => $m->year]));
        }
        $start = $p ? ['allowances' => $p->details->where('status', 1)->map(fn ($d) => ['title' => $d->title, 'amount' => (float) $d->amount])->values()->all(),
            'deductions' => $p->details->where('status', 0)->map(fn ($d) => ['title' => $d->title, 'amount' => (float) $d->amount, 'loan_id' => $d->staff_loan_id])->values()->all(), 'from' => null]
            : PayrollBook::startingLines($bid, $user_id, $m);
        $types = PayrollLineType::where('business_id', $bid)->where('status', 1)->orderBy('title')->get();

        return view('payroll.generate', ['staff' => $staff, 'month' => $m, 'p' => $p, 'start' => $start,
            'allowanceTypes' => $types->where('kind', 'allowance')->values(), 'deductionTypes' => $types->where('kind', 'deduction')->values(),
            'calc' => PayrollCalculator::calculate($bid, $staff, $m, $start['allowances'], $start['deductions'], $p, old('hours_worked', optional($p)->hours_worked))]);
    }

    public function preview(Request $request)
    {
        $this->guard('payroll-action');
        $bid = $this->biz();
        $staff = PayrollStaff::where('business_id', $bid)->where('user_id', (int) $request->input('user_id'))->firstOrFail();
        $m = $this->month($request);
        $p = Payroll::where('business_id', $bid)->where('user_id', $staff->user_id)->where('salary_month', $m->toDateString())->first();

        return response()->json(PayrollCalculator::calculate($bid, $staff, $m, (array) $request->input('allowances', []), (array) $request->input('deductions', []), $p, $request->input('hours_worked')));
    }

    public function store(Request $request)
    {
        $this->guard('payroll-action');
        $m = $this->month($request);
        try {
            $p = PayrollBook::save($this->biz(), (int) $request->input('user_id'), $m, (array) $request->input('allowances', []), (array) $request->input('deductions', []), auth()->id(), $request->input('hours_worked'));
        } catch (PayrollException $e) {
            return $this->back(false, $e->getMessage(), redirect()->back()->withInput());
        }

        return $this->back(true, __('payroll.saved', ['name' => PayrollBook::name($p)]), redirect()->action([self::class, 'index'], ['month' => $m->month, 'year' => $m->year]));
    }

    public function approve($id)
    {
        $this->guard('payroll-approve');
        try {
            PayrollBook::approve($this->payroll($id), auth()->id());
        } catch (PayrollException $e) {
            return $this->back(false, $e->getMessage());
        }

        return $this->back(true, __('payroll.approved'));
    }

    public function pay(Request $request, $id)
    {
        $this->guard('payroll-action');
        $p = $this->payroll($id);
        try {
            PayrollBook::pay($p, $request->input('pay_date') ?: today()->toDateString(), (int) $request->input('payment_account_id'), $request->input('note'), auth()->id());
        } catch (PayrollException|\RuntimeException $e) {
            return $this->back(false, $e->getMessage());
        }

        return $this->back(true, $p->fresh()->journal_entry_id ? __('payroll.paid_posted') : __('payroll.paid'));
    }

    public function unpay($id)
    {
        $this->guard('payroll-action');
        try {
            PayrollBook::unpay($this->payroll($id), auth()->id());
        } catch (PayrollException|\RuntimeException $e) {
            return $this->back(false, $e->getMessage());
        }

        return $this->back(true, __('payroll.unpaid'));
    }

    /** Generate or approve every ticked staff member's payroll. */
    public function bulk(Request $request)
    {
        $action = $request->input('action');
        $this->guard($action === 'approve' ? 'payroll-approve' : 'payroll-action');
        $bid = $this->biz();
        $m = $this->month($request);
        $done = 0;
        $skipped = [];
        foreach ((array) $request->input('users', []) as $uid) {
            $staff = PayrollStaff::where('business_id', $bid)->where('user_id', (int) $uid)->with('user')->first();
            if (! $staff) {
                continue;
            }
            $p = Payroll::where('business_id', $bid)->where('user_id', $staff->user_id)->where('salary_month', $m->toDateString())->first();
            $who = $staff->user ? trim($staff->user->first_name.' '.$staff->user->last_name) : '#'.$uid;
            try {
                if ($action === 'approve') {
                    if (! $p) {
                        throw new PayrollException(__('payroll.skip_not_generated'));
                    }
                    PayrollBook::approve($p, auth()->id());
                } else {
                    if ($p) {
                        throw new PayrollException($p->isPaid() ? __('payroll.skip_paid') : __('payroll.skip_generated'));
                    }
                    if ($staff->pay_type === 'hourly') {
                        throw new PayrollException(__('payroll.skip_hourly'));
                    }
                    $start = PayrollBook::startingLines($bid, $staff->user_id, $m);
                    PayrollBook::save($bid, $staff->user_id, $m, $start['allowances'], $start['deductions'], auth()->id());
                }
                $done++;
            } catch (PayrollException $e) {
                $skipped[] = $who.': '.$e->getMessage();
            }
        }

        return $this->bulkResult($action === 'approve' ? 'approved' : 'generated', $done, $skipped);
    }

    /** Pay every ticked payroll into the staff member's default payment account (through the review). */
    public function bulkPay(Request $request)
    {
        $this->guard('payroll-action');
        $bid = $this->biz();
        $m = $this->month($request);
        $done = 0;
        $skipped = [];
        foreach ((array) $request->input('users', []) as $uid) {
            $staff = PayrollStaff::where('business_id', $bid)->where('user_id', (int) $uid)->with('user')->first();
            if (! $staff) {
                continue;
            }
            $who = $staff->user ? trim($staff->user->first_name.' '.$staff->user->last_name) : '#'.$uid;
            $p = Payroll::where('business_id', $bid)->where('user_id', $staff->user_id)->where('salary_month', $m->toDateString())->first();
            try {
                if (! $p) {
                    throw new PayrollException(__('payroll.skip_not_generated'));
                }
                if (! $staff->payment_account_id) {
                    throw new PayrollException(__('payroll.skip_no_account'));
                }
                PayrollBook::pay($p, $request->input('pay_date') ?: today()->toDateString(), $staff->payment_account_id, $request->input('note'), auth()->id());
                $done++;
            } catch (PayrollException|\RuntimeException $e) {
                $skipped[] = $who.': '.$e->getMessage();
            }
        }

        return $this->bulkResult('paid', $done, $skipped);
    }

    private function bulkResult($what, $done, array $skipped)
    {
        $msg = __('payroll.bulk_'.$what, ['n' => $done]).($skipped ? ' '.__('payroll.bulk_skipped', ['n' => count($skipped)]) : '');

        return redirect()->back()->with('status', ['success' => $done > 0 || ! $skipped ? 1 : 0, 'msg' => $msg])->with('payroll_skipped', $skipped);
    }

    // ---------------------------------------------------------------- payslip and report

    public function payslip($id)
    {
        $this->guard('payroll-print', 'payroll-view', 'payroll-action');
        $p = $this->payroll($id);

        return view('payroll.payslip', ['p' => $p, 'staff' => PayrollStaff::where('business_id', $this->biz())->where('user_id', $p->user_id)->first(),
            'business' => \App\Business::find($this->biz()), 'account' => $p->payment_account_id ? DB::table('accounts')->where('id', $p->payment_account_id)->value('name') : null]);
    }

    public function report(Request $request)
    {
        $this->guard('payroll-report', 'payroll-view');
        $m = $this->month($request);
        $rows = Payroll::where('business_id', $this->biz())->where('salary_month', $m->toDateString())->with(['user', 'taxLines'])->get()
            ->sortBy(fn ($p) => mb_strtolower(optional($p->user)->first_name.' '.optional($p->user)->last_name))->values();
        $byTax = [];
        foreach ($rows as $p) {
            foreach ($p->taxLines as $t) {
                $byTax[$t->label] = $byTax[$t->label] ?? ['employee' => 0, 'employer' => 0, 'code' => $t->liability_code];
                $byTax[$t->label]['employee'] += (float) $t->employee_amount;
                $byTax[$t->label]['employer'] += (float) $t->employer_amount;
            }
        }

        return view('payroll.report', ['month' => $m, 'rows' => $rows, 'byTax' => $byTax]);
    }

    // ---------------------------------------------------------------- staff pay details

    public function staff()
    {
        $this->guard('payroll-setting', 'payroll-action');
        $bid = $this->biz();
        $users = User::where('business_id', $bid)->where('user_type', 'user')->whereNull('deleted_at')->orderBy('first_name')->get();
        $pay = PayrollStaff::where('business_id', $bid)->get()->keyBy('user_id');

        return view('payroll.staff', ['users' => $users, 'pay' => $pay, 'accounts' => $this->accounts()]);
    }

    public function saveStaff(Request $request, $user_id)
    {
        $this->guard('payroll-setting', 'payroll-action');
        $bid = $this->biz();
        $user = User::where('business_id', $bid)->findOrFail($user_id);
        $u = new Util();
        $acc = (int) $request->input('payment_account_id');
        if ($acc && ! DB::table('accounts')->where('business_id', $bid)->where('id', $acc)->exists()) {
            $acc = null;
        }
        $joining = $request->input('joining_date') ?: null;
        $ending = $request->input('ending_date') ?: null;
        if ($joining && $ending && $ending < $joining) {
            return $this->back(false, __('payroll.err_dates'));
        }
        PayrollStaff::updateOrCreate(['business_id' => $bid, 'user_id' => $user->id], [
            'basic_salary' => max(0, (float) $u->num_uf((string) $request->input('basic_salary', 0))),
            'pay_type' => $request->input('pay_type') === 'hourly' ? 'hourly' : 'monthly',
            'hourly_rate' => max(0, (float) $u->num_uf((string) $request->input('hourly_rate', 0))),
            'joining_date' => $joining, 'ending_date' => $ending,
            'job_title' => mb_substr(trim((string) $request->input('job_title')), 0, 120) ?: null,
            'cnps_no' => mb_substr(trim((string) $request->input('cnps_no')), 0, 60) ?: null,
            'tax_no' => mb_substr(trim((string) $request->input('tax_no')), 0, 60) ?: null,
            'payment_account_id' => $acc ?: null,
            'bank_name' => mb_substr(trim((string) $request->input('bank_name')), 0, 120) ?: null,
            'bank_account_no' => mb_substr(trim((string) $request->input('bank_account_no')), 0, 80) ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->back(true, __('payroll.staff_saved', ['name' => trim($user->first_name.' '.$user->last_name)]));
    }

    // ---------------------------------------------------------------- the bank file

    public function bankFile(Request $request)
    {
        $this->guard('payroll-action', 'payroll-report');
        $bid = $this->biz();
        $m = $this->month($request);
        $which = in_array($request->input('which'), ['unpaid', 'paid', 'all'], true) ? $request->input('which') : 'unpaid';
        $payrolls = Payroll::where('business_id', $bid)->where('salary_month', $m->toDateString())->with('user')
            ->when($which === 'unpaid', fn ($q) => $q->where('status', 0))->when($which === 'paid', fn ($q) => $q->where('status', 1))
            ->when($request->filled('users'), fn ($q) => $q->whereIn('user_id', (array) $request->input('users')))
            ->get()->sortBy(fn ($p) => mb_strtolower(optional($p->user)->first_name.' '.optional($p->user)->last_name))->values();
        // with approval required, an unpaid payroll is only ready once approved
        if (! empty(PayrollRules::settings($bid)['require_approval']) && $which !== 'paid') {
            $payrolls = $payrolls->filter(fn ($p) => $p->isPaid() || $p->isApproved())->values();
        }
        $built = PayrollBankFile::build($bid, $payrolls);
        if (! $built['rows']) {
            return $this->back(false, __('payroll.bank_nothing').($built['missing'] ? ' '.__('payroll.bank_missing', ['names' => implode(', ', $built['missing'])]) : ''));
        }
        $name = 'salaries-'.$m->format('Y-m').'-'.$which.'.csv';

        return response(PayrollBankFile::csv($bid, $built['rows']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Payroll-Rows' => (string) count($built['rows']),
            'X-Payroll-Total' => (string) $built['total'],
            'X-Payroll-Missing' => rawurlencode(implode('; ', $built['missing'])),
        ]);
    }

    // ---------------------------------------------------------------- loans and advances

    public function loans(Request $request)
    {
        $this->guard('payroll-loans', 'payroll-view');
        $bid = $this->biz();
        $status = in_array($request->input('status'), ['active', 'cleared', 'cancelled', 'all'], true) ? $request->input('status') : 'active';
        $loans = PayrollLoan::where('business_id', $bid)->with('user')->orderByDesc('given_on')->orderByDesc('id')->get()
            ->filter(function ($l) use ($status) {
                if ($status === 'all') {
                    return true;
                }
                if ($status === 'cancelled') {
                    return $l->status === 'cancelled';
                }

                return $l->status === 'active' && ($status === 'cleared' ? $l->isCleared() : ! $l->isCleared());
            })->values();
        $staff = PayrollStaff::where('business_id', $bid)->where('is_active', 1)->with('user')->get()->filter(fn ($s) => $s->user)
            ->sortBy(fn ($s) => mb_strtolower($s->user->first_name.' '.$s->user->last_name))->values();

        return view('payroll.loans', ['loans' => $loans, 'staff' => $staff, 'status' => $status, 'accounts' => $this->accounts(),
            'books' => \App\Payroll\PayrollCash::books($bid), 'advance' => PayrollRules::settings($bid)['advance_account_code']]);
    }

    public function giveLoan(Request $request)
    {
        $this->guard('payroll-loans');
        try {
            $loan = PayrollLoans::give($this->biz(), $request->only(['user_id', 'kind', 'title', 'amount', 'instalment', 'start_month', 'given_on', 'payment_account_id', 'note']), auth()->id());
        } catch (PayrollException|\RuntimeException $e) {
            return $this->back(false, $e->getMessage(), redirect()->back()->withInput());
        }

        return $this->back(true, __('payroll.loan_given', ['month' => PayrollBook::monthLabel($loan->start_month)]), redirect()->action([self::class, 'loans']));
    }

    public function cancelLoan($id)
    {
        $this->guard('payroll-loans');
        try {
            PayrollLoans::cancel(PayrollLoan::where('business_id', $this->biz())->findOrFail($id), auth()->id());
        } catch (PayrollException|\RuntimeException $e) {
            return $this->back(false, $e->getMessage());
        }

        return $this->back(true, __('payroll.loan_cancelled'));
    }

    // ---------------------------------------------------------------- remittances

    public function remittances()
    {
        $this->guard('payroll-remit', 'payroll-report');
        $bid = $this->biz();
        $months = PayrollRemittances::months($bid);

        return view('payroll.remittances', [
            'months' => $months,
            'breakdown' => collect($months)->mapWithKeys(fn ($m) => [$m['code'].'|'.$m['month'] => PayrollRemittances::breakdown($bid, $m['code'], $m['month'])]),
            'history' => PayrollRemittance::where('business_id', $bid)->with('creator')->orderByDesc('payment_date')->orderByDesc('id')->limit(200)->get(),
            'reconciliation' => PayrollRemittances::reconciliation($bid, $months),
            'accounts' => $this->accounts(),
        ]);
    }

    public function remit(Request $request)
    {
        $this->guard('payroll-remit');
        try {
            PayrollRemittances::record($this->biz(), $request->only(['code', 'month', 'amount', 'payment_date', 'payment_account_id', 'reference', 'note', 'allow_additional', 'allow_overpay']), auth()->id());
        } catch (PayrollException|\RuntimeException $e) {
            return $this->back(false, $e->getMessage());
        }

        return $this->back(true, __('payroll.remitted'));
    }

    public function voidRemit(Request $request, $id)
    {
        $this->guard('payroll-remit');
        try {
            PayrollRemittances::void(PayrollRemittance::where('business_id', $this->biz())->findOrFail($id), $request->input('reason'), auth()->id());
        } catch (PayrollException|\RuntimeException $e) {
            return $this->back(false, $e->getMessage());
        }

        return $this->back(true, __('payroll.remit_voided'));
    }

    // ---------------------------------------------------------------- settings

    public function settings()
    {
        $this->guard('payroll-setting');
        $bid = $this->biz();

        return view('payroll.settings', [
            's' => PayrollRules::settings($bid),
            'types' => PayrollLineType::where('business_id', $bid)->orderBy('kind')->orderBy('title')->get(),
            'groups' => PayrollTaxGroup::where('business_id', $bid)->with('bands')->orderBy('display_order')->orderBy('id')->get(),
            'singles' => PayrollTaxSetting::where('business_id', $bid)->whereNull('tax_group_id')->orderBy('bracket_order')->orderBy('id')->get(),
            'hasTaxes' => PayrollRules::hasTaxes($bid),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->guard('payroll-setting');
        PayrollRules::saveSettings($this->biz(), [
            'require_approval' => $request->boolean('require_approval') ? 1 : 0,
            'carry_over_lines' => $request->boolean('carry_over_lines') ? 1 : 0,
            'prorate_partial_months' => $request->boolean('prorate_partial_months') ? 1 : 0,
            'proration_basis' => $request->input('proration_basis') === 'working_days' ? 'working_days' : 'calendar_days',
            'advance_account_code' => preg_match('/^\d{2,10}$/', (string) $request->input('advance_account_code')) ? $request->input('advance_account_code') : '421',
            'bank_file_columns' => array_values(array_intersect(PayrollRules::BANK_COLUMNS, (array) $request->input('bank_file_columns', []))) ?: PayrollRules::DEFAULTS['bank_file_columns'],
            'bank_file_delimiter' => in_array($request->input('bank_file_delimiter'), [',', ';', 'tab'], true) ? $request->input('bank_file_delimiter') : ',',
            'bank_file_header' => $request->boolean('bank_file_header') ? 1 : 0,
            'bank_file_reference' => mb_substr(trim((string) $request->input('bank_file_reference')), 0, 60) ?: PayrollRules::DEFAULTS['bank_file_reference'],
            'bank_file_decimals' => (int) $request->input('bank_file_decimals') === 2 ? 2 : 0,
        ]);

        return $this->back(true, __('payroll.settings_saved'));
    }

    public function installTaxes()
    {
        $this->guard('payroll-setting');
        $ok = PayrollRules::installCameroonTaxes($this->biz());

        return $this->back($ok, $ok ? __('payroll.taxes_installed') : __('payroll.taxes_exist'));
    }

    /** Rates, bounds and amounts of the taxes, in one save (bands and single taxes). */
    public function saveTaxes(Request $request)
    {
        $this->guard('payroll-setting');
        $bid = $this->biz();
        $u = new Util();
        $n = fn ($v) => (float) $u->num_uf((string) $v);
        DB::transaction(function () use ($request, $bid, $n) {
            foreach ((array) $request->input('groups', []) as $id => $row) {
                $g = PayrollTaxGroup::where('business_id', $bid)->find($id);
                if ($g) {
                    $g->fill(['title' => mb_substr(trim((string) ($row['title'] ?? $g->title)), 0, 150) ?: $g->title, 'status' => ! empty($row['status']),
                        'liability_code' => in_array($row['liability_code'] ?? '', ['431', '447', '442'], true) ? $row['liability_code'] : $g->liability_code])->save();
                }
            }
            foreach ((array) $request->input('bands', []) as $id => $row) {
                $b = PayrollTaxSetting::where('business_id', $bid)->find($id);
                if (! $b) {
                    continue;
                }
                $b->fill([
                    'min_amount' => $n($row['min_amount'] ?? $b->min_amount), 'max_amount' => $n($row['max_amount'] ?? $b->max_amount),
                    'percentage' => $n($row['percentage'] ?? $b->percentage), 'employer_percentage' => $n($row['employer_percentage'] ?? $b->employer_percentage),
                    'fixed_amount' => $n($row['fixed_amount'] ?? $b->fixed_amount), 'employer_fixed_amount' => $n($row['employer_fixed_amount'] ?? $b->employer_fixed_amount),
                    'threshold' => $n($row['threshold'] ?? $b->threshold), 'status' => ! empty($row['status']),
                ])->save();
            }
        });

        return $this->back(true, __('payroll.taxes_saved'));
    }

    public function addType(Request $request)
    {
        $this->guard('payroll-setting');
        $title = trim((string) $request->input('title'));
        $kind = $request->input('kind') === 'deduction' ? 'deduction' : 'allowance';
        if ($title === '') {
            return $this->back(false, __('payroll.err_type_title'));
        }
        PayrollLineType::create(['business_id' => $this->biz(), 'kind' => $kind, 'title' => mb_substr($title, 0, 150),
            'is_taxable' => $kind === 'allowance' && $request->boolean('is_taxable'), 'status' => 1]);

        return $this->back(true, __('payroll.type_added', ['title' => $title]));
    }

    public function toggleType($id)
    {
        $this->guard('payroll-setting');
        $t = PayrollLineType::where('business_id', $this->biz())->findOrFail($id);
        $t->status = ! $t->status;
        $t->save();

        return $this->back(true, __('payroll.type_saved'));
    }
}
