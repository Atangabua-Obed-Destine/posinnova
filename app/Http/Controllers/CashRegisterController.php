<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\CashRegister;
use App\Utils\CashRegisterUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $cashRegisterUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  CashRegisterUtil  $cashRegisterUtil
     * @return void
     */
    public function __construct(CashRegisterUtil $cashRegisterUtil, ModuleUtil $moduleUtil)
    {
        $this->cashRegisterUtil = $cashRegisterUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('cash_register.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //like:repair
        $sub_type = request()->get('sub_type');
        //PosInnova: opened from Patient accounts -> go back there afterwards
        if (request()->get('return_to') == 'accounts') {
            request()->session()->put('register_return_to', 'accounts');
        }

        //Check if there is a open register, if yes then redirect to POS screen.
        if ($this->cashRegisterUtil->countOpenedRegister() != 0) {
            return $this->afterOpenRedirect($sub_type);
        }
        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id);

        // PosInnova hospital shifts: when enabled the opening float is auto-zero
        // and the screen turns into a one-click "Start <shift> Shift".
        $shift_enabled = $this->cashRegisterUtil->isShiftEnabled();
        $shift_settings = $this->cashRegisterUtil->getShiftSettings();
        $current_shift = $shift_enabled ? $this->cashRegisterUtil->shiftFor() : null;
        $current_shift_label = $this->cashRegisterUtil->shiftLabel($current_shift);

        return view('cash_register.create')->with(compact(
            'business_locations', 'sub_type',
            'shift_enabled', 'shift_settings', 'current_shift', 'current_shift_label'
        ));
    }

    /**
     * PosInnova: where to go once the register is open. The POS for those who sell;
     * a clinic cashier without POS access goes to the patient accounts instead of
     * landing on an "Unauthorized" page right after starting the shift.
     */
    private function afterOpenRedirect($sub_type)
    {
        $user = auth()->user();
        $business_id = request()->session()->get('user.business_id');
        $return_to = request()->session()->pull('register_return_to');
        if ($return_to == 'accounts' && in_array('hospital', (array) request()->session()->get('business.enabled_modules', [])) && $user->can('patient.view_bill')) {
            return redirect()->action([\App\Http\Controllers\ClinicAccountController::class, 'index']);
        }
        $can_pos = $user->can('superadmin') || $user->can('sell.create')
            || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && $user->can('repair.create'));
        if ($can_pos) {
            return redirect()->action([\App\Http\Controllers\SellPosController::class, 'create'], ['sub_type' => $sub_type]);
        }
        $enabled_modules = (array) request()->session()->get('business.enabled_modules', []);
        if (in_array('hospital', $enabled_modules) && $user->can('patient.view_bill')) {
            return redirect()->action([\App\Http\Controllers\ClinicAccountController::class, 'index']);
        }

        return redirect('/home');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //like:repair
        $sub_type = request()->get('sub_type');
        $shift_welcome = null;

        try {
            // PosInnova hospital shifts: each shift opens with an empty drawer.
            $shift_enabled = $this->cashRegisterUtil->isShiftEnabled();
            $shift = $shift_enabled ? $this->cashRegisterUtil->shiftFor() : null;

            $initial_amount = 0;
            if (! $shift_enabled && ! empty($request->input('amount'))) {
                $initial_amount = $this->cashRegisterUtil->num_uf($request->input('amount'));
            }
            $user_id = $request->session()->get('user.id');
            $business_id = $request->session()->get('user.business_id');

            $register = CashRegister::create([
                'business_id' => $business_id,
                'user_id' => $user_id,
                'status' => 'open',
                'shift' => $shift,
                'location_id' => $request->input('location_id'),
                'created_at' => \Carbon::now()->format('Y-m-d H:i:00'),
            ]);
            if (! empty($initial_amount)) {
                $register->cash_register_transactions()->create([
                    'amount' => $initial_amount,
                    'pay_method' => 'cash',
                    'type' => 'credit',
                    'transaction_type' => 'initial',
                ]);
            }

            // PosInnova hospital shifts: welcome the cashier into the shift.
            if ($shift_enabled && ! empty($shift)) {
                $shift_settings = $this->cashRegisterUtil->getShiftSettings();
                $window = $shift === 'morning'
                    ? $shift_settings['morning_start'].'–'.$shift_settings['morning_end']
                    : $shift_settings['night_start'].'–'.$shift_settings['night_end'];
                $shift_welcome = ['success' => 1, 'msg' => __('lang_v1.joined_shift', [
                    'shift' => $this->cashRegisterUtil->shiftLabel($shift),
                    'window' => $window,
                ])];
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }

        $redirect = $this->afterOpenRedirect($sub_type);
        if (! empty($shift_welcome)) {
            $redirect->with('status', $shift_welcome);
        }

        return $redirect;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\CashRegister  $cashRegister
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('view_cash_register')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $register_details = $this->cashRegisterUtil->getRegisterDetails($id);
        $user_id = $register_details->user_id;
        $open_time = $register_details['open_time'];
        $close_time = ! empty($register_details['closed_at']) ? $register_details['closed_at'] : \Carbon::now()->toDateTimeString();
        $details = $this->cashRegisterUtil->getRegisterTransactionDetails($user_id, $open_time, $close_time);

        $payment_types = $this->cashRegisterUtil->payment_types(null, false, $business_id);

        return view('cash_register.register_details')
                    ->with(compact('register_details', 'details', 'payment_types', 'close_time'));
    }

    /**
     * Shows register details modal.
     *
     * @param  void
     * @return \Illuminate\Http\Response
     */
    public function getRegisterDetails()
    {
        if (! auth()->user()->can('view_cash_register')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $register_details = $this->cashRegisterUtil->getRegisterDetails();

        $user_id = auth()->user()->id;
        $open_time = $register_details['open_time'];
        $close_time = \Carbon::now()->toDateTimeString();

        $is_types_of_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

        $details = $this->cashRegisterUtil->getRegisterTransactionDetails($user_id, $open_time, $close_time, $is_types_of_service_enabled);

        $payment_types = $this->cashRegisterUtil->payment_types($register_details->location_id, true, $business_id);

        return view('cash_register.register_details')
                ->with(compact('register_details', 'details', 'payment_types', 'close_time'));
    }

    /**
     * Shows close register form.
     *
     * @param  void
     * @return \Illuminate\Http\Response
     */
    public function getCloseRegister($id = null)
    {
        if (! auth()->user()->can('close_cash_register')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $register_details = $this->cashRegisterUtil->getRegisterDetails($id);

        $user_id = $register_details->user_id;
        $open_time = $register_details['open_time'];
        $close_time = \Carbon::now()->toDateTimeString();

        $is_types_of_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

        $details = $this->cashRegisterUtil->getRegisterTransactionDetails($user_id, $open_time, $close_time, $is_types_of_service_enabled);

        $payment_types = $this->cashRegisterUtil->payment_types($register_details->location_id, true, $business_id);

        $pos_settings = ! empty(request()->session()->get('business.pos_settings')) ? json_decode(request()->session()->get('business.pos_settings'), true) : [];

        return view('cash_register.close_register_modal')
                    ->with(compact('register_details', 'details', 'payment_types', 'pos_settings'));
    }

    /**
     * Closes currently opened register.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function postCloseRegister(Request $request)
    {
        if (! auth()->user()->can('close_cash_register')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            //Disable in demo
            if (config('app.env') == 'demo') {
                $output = ['success' => 0,
                    'msg' => 'Feature disabled in demo!!',
                ];

                return redirect()->action([\App\Http\Controllers\HomeController::class, 'index'])->with('status', $output);
            }

            $input = $request->only(['closing_amount', 'total_card_slips', 'total_cheques', 'closing_note']);
            $input['closing_amount'] = $this->cashRegisterUtil->num_uf($input['closing_amount']);
            $user_id = $request->input('user_id');
            $input['closed_at'] = \Carbon::now()->format('Y-m-d H:i:s');
            $input['status'] = 'close';
            $input['denominations'] = ! empty(request()->input('denominations')) ? json_encode(request()->input('denominations')) : null;

            CashRegister::where('user_id', $user_id)
                                ->where('status', 'open')
                                ->update($input);
            $output = ['success' => 1,
                'msg' => __('cash_register.close_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * PosInnova hospital shifts — cashier chose to extend the current shift
     * past its end time. Marks the open register so the prompt stops firing.
     */
    public function extendShift(Request $request)
    {
        $user_id = $request->session()->get('user.id');
        $register = $this->cashRegisterUtil->getCurrentCashRegister($user_id);
        if (empty($register)) {
            return response()->json(['success' => 0, 'msg' => __('lang_v1.no_open_shift')], 422);
        }

        if (empty($register->extended_at)) {
            $register->extended_at = \Carbon::now();
            $register->save();
        }

        return response()->json(['success' => 1, 'msg' => __('lang_v1.shift_extended_msg')]);
    }

    /**
     * PosInnova hospital shifts — close the current shift's register and open
     * a fresh zero-drawer register for the next shift.
     */
    public function startNextShift(Request $request)
    {
        if (! $this->cashRegisterUtil->isShiftEnabled()) {
            return response()->json(['success' => 0, 'msg' => __('lang_v1.shift_feature_disabled')], 422);
        }

        $user_id = $request->session()->get('user.id');
        $business_id = $request->session()->get('user.business_id');

        $register = $this->cashRegisterUtil->getCurrentCashRegister($user_id);
        if (empty($register)) {
            return response()->json(['success' => 0, 'msg' => __('lang_v1.no_open_shift')], 422);
        }

        // The cashier's current (possibly stale/extended) shift, used only for
        // the "is it over yet?" guard below.
        $current_shift = $register->shift ?: $this->cashRegisterUtil->shiftFor();

        // The shift to start is the one the SYSTEM says is active RIGHT NOW —
        // not a mechanical morning→night flip. If a cashier extends a shift and
        // returns later (e.g. the next day when the same shift is active again),
        // this starts the actual current shift instead of jumping to a shift
        // that has already passed. Falls back to the mechanical next shift only
        // if the clock can't resolve one (shouldn't happen while enabled).
        $new_shift = $this->cashRegisterUtil->shiftFor() ?: $this->cashRegisterUtil->nextShift($current_shift);

        // Guard: the next shift can only be started once the current shift's
        // time is actually up — or the cashier has extended it (and now wants
        // to close out). This blocks premature switching that would misattribute
        // later sales. Mirrors the UI gate; authoritative so it can't be bypassed.
        if (empty($register->extended_at)) {
            $ends_at = $this->cashRegisterUtil->shiftEndsAtForRegister($register);
            if (empty($ends_at)) {
                $ends_at = $this->cashRegisterUtil->shiftEndsAt($current_shift, $register->created_at);
            }
            if (! empty($ends_at) && \Carbon::now()->lt($ends_at)) {
                return response()->json(['success' => 0, 'msg' => __('lang_v1.shift_not_over_yet')], 422);
            }
        }

        \DB::beginTransaction();
        try {
            // Personal transition: close ONLY this cashier's own register.
            // Other cashiers stay in their current shift until each advances
            // individually — nobody can force-close another's drawer.
            $register->status = 'close';
            $register->closed_at = \Carbon::now()->format('Y-m-d H:i:s');
            $register->closing_note = __('lang_v1.auto_closed_shift_handover');
            $register->save();

            // Open a fresh, empty drawer for the current user under the next shift.
            CashRegister::create([
                'business_id' => $business_id,
                'user_id' => $user_id,
                'status' => 'open',
                'shift' => $new_shift,
                'location_id' => $register->location_id,
                'created_at' => \Carbon::now()->format('Y-m-d H:i:00'),
            ]);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return response()->json(['success' => 0, 'msg' => __('messages.something_went_wrong')], 500);
        }

        return response()->json([
            'success' => 1,
            'msg' => __('lang_v1.shift_started_msg', ['shift' => $this->cashRegisterUtil->shiftLabel($new_shift)]),
            'shift' => $new_shift,
        ]);
    }
}
