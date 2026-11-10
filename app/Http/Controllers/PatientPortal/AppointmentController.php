<?php

namespace App\Http\Controllers\PatientPortal;

use App\ClinicAppointment;
use App\Http\Controllers\Controller;
use App\Utils\ClinicAppointments;
use App\Utils\ClinicSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Patient portal: the patient's appointments. A patient asks for a free slot
 * (status requested); reception confirms it. Patients see and cancel only
 * their own bookings.
 */
class AppointmentController extends Controller
{
    private function patient()
    {
        return Auth::guard('patient')->user();
    }

    public function index()
    {
        $patient = $this->patient();
        $bid = $patient->business_id;
        if (! ClinicAppointments::enabled($bid)) {
            abort(404);
        }
        $today = Carbon::today()->toDateString();
        $upcoming = ClinicAppointment::forBusiness($bid)->where('contact_id', $patient->id)
            ->whereDate('date', '>=', $today)->whereIn('status', ['requested', 'confirmed', 'checked_in'])
            ->with('doctor')->orderBy('date')->orderBy('start_time')->get();
        $past = ClinicAppointment::forBusiness($bid)->where('contact_id', $patient->id)
            ->where(function ($q) use ($today) {
                $q->whereDate('date', '<', $today)->orWhereIn('status', ['cancelled', 'declined', 'no_show']);
            })
            ->with('doctor')->orderByDesc('date')->orderByDesc('start_time')->limit(15)->get();
        $can_book = ClinicAppointments::portalBooking($bid) && ! empty(ClinicAppointments::doctorsWithHours($bid));

        return view('patient_portal.appointments', compact('patient', 'upcoming', 'past', 'can_book'));
    }

    public function create()
    {
        $patient = $this->patient();
        $bid = $patient->business_id;
        if (! ClinicAppointments::portalBooking($bid)) {
            abort(404);
        }
        $doctors = ClinicAppointments::doctorsWithHours($bid);
        $days_ahead = (int) ClinicSettings::get($bid, 'appointments_days_ahead');

        return view('patient_portal.appointment_create', compact('patient', 'doctors', 'days_ahead'));
    }

    /** Free slots of a doctor on a date (JSON). */
    public function slots(Request $request)
    {
        $patient = $this->patient();
        $bid = $patient->business_id;
        if (! ClinicAppointments::portalBooking($bid)) {
            abort(404);
        }
        $doctor_id = (int) $request->input('doctor_id');
        if (! isset(ClinicAppointments::doctorsWithHours($bid)[$doctor_id])) {
            return ['slots' => []];
        }
        try {
            $date = Carbon::parse($request->input('date'))->startOfDay();
        } catch (\Exception $e) {
            return ['slots' => []];
        }
        $days_ahead = (int) ClinicSettings::get($bid, 'appointments_days_ahead');
        if ($date->lt(Carbon::today()) || $date->gt(Carbon::today()->addDays($days_ahead))) {
            return ['slots' => []];
        }
        $notice = (int) ClinicSettings::get($bid, 'appointments_min_notice_hours');
        $free = collect(ClinicAppointments::slots($bid, $doctor_id, $date, $notice))
            ->where('state', 'free')->pluck('start')->values();

        return ['slots' => $free, 'day_off' => ClinicAppointments::isDayOff($bid, $doctor_id, $date)];
    }

    public function store(Request $request)
    {
        $patient = $this->patient();
        $bid = $patient->business_id;
        if (! ClinicAppointments::portalBooking($bid)) {
            abort(404);
        }
        if (! isset(ClinicAppointments::doctorsWithHours($bid)[(int) $request->input('doctor_id')])) {
            return back()->withInput()->with('status', ['success' => false, 'msg' => __('clinic.choose_doctor')]);
        }
        [$appt, $error] = ClinicAppointments::book($bid, $patient->id, $request->input('doctor_id'), $request->input('date'),
            (string) $request->input('start'), 'portal', null, $request->input('reason'));
        if ($error) {
            return back()->withInput()->with('status', ['success' => false, 'msg' => $error]);
        }

        return redirect()->route('portal.appointments')->with('status', ['success' => true, 'msg' => __('clinic.appt_portal_requested')]);
    }

    public function cancel($id)
    {
        $patient = $this->patient();
        $appt = ClinicAppointment::forBusiness($patient->business_id)->where('contact_id', $patient->id)->findOrFail($id);
        if (! in_array($appt->status, ClinicAppointment::OPEN) || $appt->startsAt()->isPast()) {
            return redirect()->route('portal.appointments')->with('status', ['success' => false, 'msg' => __('clinic.appt_not_open')]);
        }
        ClinicAppointments::release($appt, 'cancelled', null, __('clinic.appt_cancelled_by_patient'));

        return redirect()->route('portal.appointments')->with('status', ['success' => true, 'msg' => __('clinic.appt_portal_cancelled')]);
    }
}
