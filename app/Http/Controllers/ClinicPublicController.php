<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\ClinicWhatsappAccount;
use App\Utils\ClinicAppointments;
use App\Utils\ClinicDoctors;
use App\Utils\ClinicSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The public, no-login clinic page at /c/{slug}: the clinic's details, its
 * doctors and their open slots, a map, a WhatsApp link, and booking. Returning
 * patients are sent to their portal; a NEW patient leaves name + phone as a
 * request (source 'public', no account) that reception approves. Nothing here
 * reads session('business.*') — the business is resolved from the slug and
 * passed to the view explicitly. Off unless the clinic sets a slug and enables it.
 */
class ClinicPublicController extends Controller
{
    /** Resolve the clinic from the slug, or 404 when unknown / not public. */
    private function clinic($slug)
    {
        $business = Business::where('slug', $slug)->first();
        if (! $business) {
            abort(404);
        }
        $raw = $business->enabled_modules;
        $mods = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        if (! in_array('hospital', $mods, true)
            || ! ClinicSettings::get($business->id, 'public_page_enabled')
            || ! ClinicAppointments::enabled($business->id)) {
            abort(404);
        }

        return $business;
    }

    /** Which doctors to show: the chosen set, else all with hours. */
    private function publicDoctors($business_id)
    {
        $all = ClinicAppointments::doctorsWithHours($business_id);
        $chosen = (array) ClinicSettings::get($business_id, 'public_doctors');
        if (! empty($chosen)) {
            $all = array_intersect_key($all, array_flip(array_map('intval', $chosen)));
        }

        return $all;
    }

    /** A wa.me click-to-chat link into the clinic's WhatsApp (the bot), or null. */
    private function whatsappLink($business, $location)
    {
        $acc = ClinicWhatsappAccount::where('business_id', $business->id)->first();
        $num = $acc && $acc->display_phone ? preg_replace('/\D+/', '', $acc->display_phone) : null;
        if (! $num && $location && $location->mobile) {
            $num = \App\Utils\ClinicWhatsApp::waNumber($business->id, $location->mobile);
        }

        return $num ? 'https://wa.me/'.$num.'?text='.rawurlencode(__('clinic.pub_wa_prefill')) : null;
    }

    public function show(Request $request, $slug, $doctor = null)
    {
        $business = $this->clinic($slug);
        $bid = $business->id;
        $location = BusinessLocation::where('business_id', $bid)->orderBy('id')->first();
        $doctors = $this->publicDoctors($bid);
        $days_ahead = (int) ClinicSettings::get($bid, 'appointments_days_ahead');
        $notice = (int) ClinicSettings::get($bid, 'appointments_min_notice_hours');

        // for each doctor, the next few open days (lightweight preview)
        $schedules = [];
        foreach ($doctors as $id => $name) {
            $days = [];
            for ($i = 0; $i <= $days_ahead && count($days) < 6; $i++) {
                $d = Carbon::today()->addDays($i);
                if (ClinicAppointments::isDayOff($bid, $id, $d) || ! ClinicAppointments::dayHasRoom($bid, $id, $d)) {
                    continue;
                }
                $free = collect(ClinicAppointments::slots($bid, $id, $d, $notice))->where('state', 'free');
                if ($free->isEmpty()) {
                    continue;
                }
                $days[] = ['date' => $d->toDateString(), 'label' => $d->locale(app()->getLocale())->isoFormat('ddd D MMM'), 'free' => $free->count()];
            }
            $schedules[$id] = ['name' => $name, 'days' => $days];
        }

        // a doctor's own link: only him (the others are one button away); an unknown id opens the clinic page
        $selected = null;
        $solo = false;
        if ($doctor !== null) {
            if (! isset($doctors[(int) $doctor])) {
                return redirect()->route('clinic-public.show', $slug);
            }
            $selected = (int) $doctor;
            $solo = true;
        }
        $specialties = ClinicDoctors::specialties(array_keys($doctors));
        foreach ($schedules as $id => $row) {
            $schedules[$id]['specialty'] = $specialties[$id] ?? null;
        }
        $data = [
            'business' => $business,
            'location' => $location,
            'address' => $location ? $location->location_address : null,
            'doctors' => $doctors,
            'schedules' => $schedules,
            'selected' => $selected,
            'solo' => $solo,
            'specialties' => $specialties,
            'tagline' => ClinicSettings::get($bid, 'public_tagline'),
            'description' => ClinicSettings::get($bid, 'public_description'),
            'map_lat' => ClinicSettings::get($bid, 'public_map_lat'),
            'map_lng' => ClinicSettings::get($bid, 'public_map_lng'),
            'whatsapp' => $this->whatsappLink($business, $location),
            'portal_url' => url('portal'),
            'slug' => $slug,
            'days_ahead' => $days_ahead,
        ];

        return view('clinic_public.show', $data);
    }

    /** Free slots for a doctor on a date (JSON), honouring notice + daily cap. */
    public function slots(Request $request, $slug)
    {
        $business = $this->clinic($slug);
        $bid = $business->id;
        $doctor_id = (int) $request->input('doctor_id');
        if (! isset($this->publicDoctors($bid)[$doctor_id])) {
            return response()->json(['slots' => []]);
        }
        try {
            $date = Carbon::parse($request->input('date'))->startOfDay();
        } catch (\Exception $e) {
            return response()->json(['slots' => []]);
        }
        $days_ahead = (int) ClinicSettings::get($bid, 'appointments_days_ahead');
        if ($date->lt(Carbon::today()) || $date->gt(Carbon::today()->addDays($days_ahead))) {
            return response()->json(['slots' => []]);
        }
        if (! ClinicAppointments::dayHasRoom($bid, $doctor_id, $date)) {
            return response()->json(['slots' => [], 'full' => true]);
        }
        $notice = (int) ClinicSettings::get($bid, 'appointments_min_notice_hours');
        $free = collect(ClinicAppointments::slots($bid, $doctor_id, $date, $notice))
            ->where('state', 'free')->pluck('start')->values();

        return response()->json(['slots' => $free]);
    }

    /** A new patient's booking request from the page (guest). */
    public function book(Request $request, $slug)
    {
        $business = $this->clinic($slug);
        $bid = $business->id;
        // honeypot: real people leave it empty
        if ($request->filled('website')) {
            return back()->with('pub_status', ['ok' => false, 'msg' => __('clinic.pub_book_error')]);
        }
        $doctor_id = (int) $request->input('doctor_id');
        if (! isset($this->publicDoctors($bid)[$doctor_id])) {
            return back()->withInput()->with('pub_status', ['ok' => false, 'msg' => __('clinic.choose_doctor')]);
        }
        [$appt, $error] = ClinicAppointments::bookGuest(
            $bid, $doctor_id, $request->input('date'), (string) $request->input('start'),
            (string) $request->input('guest_name'), (string) $request->input('guest_phone'), $request->input('reason')
        );
        if ($error) {
            // which part of the page the error is about: a field, or the chosen time
            $field = $error === __('clinic.appt_guest_phone_required') ? 'guest_phone'
                : ($error === __('clinic.appt_guest_name_required') ? 'guest_name' : 'slot');

            return back()->withInput()->with('pub_status', ['ok' => false, 'msg' => $error, 'field' => $field]);
        }
        \App\Utils\ClinicWhatsApp::notifyDoctorRequest($appt);

        // what the visitor booked, for the confirmation screen
        $done = [
            'doctor' => $this->publicDoctors($bid)[$doctor_id] ?? '',
            'date' => Carbon::parse($appt->date)->locale(app()->getLocale())->isoFormat('dddd D MMMM'),
            'time' => $appt->start,
            'name' => $appt->guest_name,
        ];

        // back where the patient booked from: the doctor's own link or the clinic page
        $back = (int) $request->input('from_doctor') === $doctor_id
            ? route('clinic-public.doctor', [$slug, $doctor_id]) : route('clinic-public.show', $slug);

        return redirect()->to($back)->with('pub_done', $done);
    }
}
