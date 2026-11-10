<?php

namespace App\Http\Controllers;

use App\Utils\ClinicQueue;
use App\Utils\Util;
use App\Visit;
use Illuminate\Http\Request;

/**
 * The doctor's queue (clinic phase 2): today's checked-in patients for a
 * doctor, in queue order, with the package and whether the check-in invoice
 * is paid. The doctor calls the next patient in, which opens the consultation.
 *
 * When the clinic takes payment before the doctor (Clinic settings), an unpaid
 * patient cannot be called in until the cashier takes the payment, or someone
 * with visit.override_pay_first lets them through (logged).
 */
class ClinicQueueController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    private function checkHospitalEnabled()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.receive_patients')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $user_id = (int) $request->session()->get('user.id');
        $doctors = ClinicQueue::doctors($business_id);
        $see_all = auth()->user()->can('consultation.view_all');

        // Doctors see their own queue; with consultation.view_all they may
        // look at another doctor's or at everyone's ('all').
        $doctor_id = $see_all ? $request->input('doctor_id', isset($doctors[$user_id]) ? $user_id : 'all') : $user_id;

        $today = \Carbon::today()->toDateString();
        $query = Visit::forBusiness($business_id)
            ->whereDate('visit_date', $today)
            ->whereNotNull('queue_no')
            ->with(['patient.patientDetails', 'package', 'feeTransaction', 'doctor', 'appointment']);
        if ($doctor_id !== 'all') {
            $query->where('doctor_id', (int) $doctor_id);
        }
        $visits = $query->orderBy('queue_no')->get();

        $booked_first = (bool) \App\Utils\ClinicSettings::get($business_id, 'appointments_booked_first');
        $active = $visits->filter(function ($v) {
            return $v->status == 'open' && in_array($v->queue_status, ['with_doctor', 'waiting']);
        })->sortBy(function ($v) use ($booked_first) {
            // with the doctor first; then (setting) booked patients by their time; then walk-ins by number
            if ($v->queue_status == 'with_doctor') {
                return sprintf('0-%06d', $v->queue_no);
            }
            if ($booked_first && $v->appointment) {
                return '1-'.$v->appointment->start.'-'.sprintf('%06d', $v->queue_no);
            }

            return sprintf('2-%06d', $v->queue_no);
        })->values();
        $done = $visits->filter(function ($v) {
            return ! ($v->status == 'open' && in_array($v->queue_status, ['with_doctor', 'waiting']));
        })->values();

        foreach ($active as $v) {
            $v->pay_state = ClinicQueue::payState($v);
            $v->pay_blocks = ClinicQueue::payGateBlocks($v);
        }

        $data = compact('active', 'done', 'doctors', 'doctor_id', 'see_all');

        if ($request->ajax() || $request->boolean('partial')) {
            return view('clinic.queue.partials.list', $data);
        }

        return view('clinic.queue.index', $data);
    }

    /** Call the patient in: opens the consultation. */
    public function start($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.receive_patients') || ! auth()->user()->can('consultation.create')) {
            abort(403, 'Unauthorized action.');
        }

        $visit = $this->queuedVisit($id);

        // A refused call-in lands on the queue, never on the page before it.
        if (! in_array($visit->queue_status, ['waiting', 'with_doctor']) || $visit->status != 'open') {
            return redirect()->action([self::class, 'index'])->with('status', ['success' => false, 'msg' => __('clinic.not_in_queue')]);
        }
        if (ClinicQueue::payGateBlocks($visit)) {
            return redirect()->action([self::class, 'index'])->with('status', ['success' => false, 'msg' => __('clinic.pay_first_blocked')]);
        }

        if ($visit->queue_status == 'waiting') {
            $visit->queue_status = 'with_doctor';
            $visit->called_at = now();
            $visit->save();
        }

        return redirect()->action([\App\Http\Controllers\ConsultationController::class, 'create'], ['visit_id' => $visit->id]);
    }

    /** Let an unpaid patient see the doctor when the clinic takes payment first. */
    public function letThrough($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.override_pay_first')) {
            abort(403, 'Unauthorized action.');
        }

        $visit = $this->queuedVisit($id);
        if ($visit->status == 'open' && empty($visit->pay_override_at)) {
            $visit->pay_override_by = request()->session()->get('user.id');
            $visit->pay_override_at = now();
            $visit->save();
            $this->commonUtil->activityLog($visit, 'pay_first_override', null, ['ref_no' => $visit->ref_no, 'queue_no' => $visit->queue_no]);
        }

        return $this->back(true, __('clinic.let_through_done'));
    }

    /** The patient was called and is not there. */
    public function noShow($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.receive_patients') && ! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $visit = $this->queuedVisit($id);
        if ($visit->status == 'open' && $visit->queue_status == 'waiting') {
            $visit->queue_status = 'no_show';
            $visit->save();
        }

        return $this->back(true, __('clinic.marked_no_show'));
    }

    /** A no-show patient turned up after all: back in the queue. */
    public function requeue($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.receive_patients') && ! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $visit = $this->queuedVisit($id);
        if ($visit->status == 'open' && $visit->queue_status == 'no_show') {
            $visit->queue_status = 'waiting';
            $visit->save();
        }

        return $this->back(true, __('clinic.back_in_queue'));
    }

    private function queuedVisit($id)
    {
        return Visit::forBusiness(request()->session()->get('user.business_id'))
            ->whereNotNull('queue_no')
            ->findOrFail($id);
    }

    private function back($success, $msg)
    {
        $output = ['success' => $success, 'msg' => $msg];
        if (request()->ajax()) {
            return $output;
        }

        return redirect()->back()->with('status', $output);
    }
}
