<?php

namespace App\Utils;

use App\Admission;
use App\ClinicAppointment;
use App\Consultation;
use App\LabRequest;
use App\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data for the hospital home page (/home when the hospital module is on).
 * Every tile and list is built only when the user may open the screen behind it,
 * and uses the same rules as that screen, so the numbers always match:
 * the doctor queue, the dispensary queue, patient accounts, lab requests and the
 * appointments diary.
 */
class ClinicDashboard
{
    const LIST_SIZE = 8;

    /**
     * May the user open the "Pharmacy sales" tab (the usual sales dashboard)? dashboard.data is
     * ticked for most roles, but that dashboard only has content for the admin and for roles
     * that see sales, purchases or stock.
     */
    public static function canSeeSales($user, $business_id)
    {
        return $user->can('dashboard.data') && ($user->hasRole('Admin#'.$business_id) || $user->can('superadmin')
            || $user->can('sell.view') || $user->can('direct_sell.view') || $user->can('purchase.view') || $user->can('stock_report.view'));
    }

    public static function build($business_id, $user)
    {
        $today = Carbon::today();
        $d = [
            'tiles' => [],
            'panels' => [],
            'actions' => [],
            'can_search' => $user->can('patient.view'),
            'can_sales' => self::canSeeSales($user, $business_id),
        ];
        $on = function ($section) use ($business_id) {
            return ClinicSettings::sectionOn($business_id, $section);
        };

        // ---------- quick actions
        if ($user->can('patient.create')) {
            $d['actions'][] = ['url' => action([\App\Http\Controllers\PatientController::class, 'create']), 'label' => __('clinic.dash_register_patient'), 'icon' => 'user-plus', 'primary' => true];
        }
        if ($user->can('visit.create')) {
            $d['actions'][] = ['url' => action([\App\Http\Controllers\VisitController::class, 'create']), 'label' => __('clinic.check_in_patient'), 'icon' => 'door-enter', 'primary' => true];
        }
        $appts_on = ClinicAppointments::enabled($business_id);
        if ($appts_on && $user->can('appointment.manage')) {
            $d['actions'][] = ['url' => action([\App\Http\Controllers\ClinicAppointmentController::class, 'index']), 'label' => __('clinic.dash_book_appointment'), 'icon' => 'calendar-event'];
        }
        // shortcuts to a work screen, for staff who do not work the front desk (the admin and
        // reception reach those screens from the tiles and lists below)
        $doctors = ClinicQueue::doctors($business_id);
        $is_doctor = isset($doctors[$user->id]);
        $front_desk = $user->can('visit.create');
        if (! $front_desk && $user->can('visit.receive_patients')) {
            $d['actions'][] = ['url' => action([\App\Http\Controllers\ClinicQueueController::class, 'index']), 'label' => $is_doctor ? __('clinic.dash_open_queue') : __('clinic.doctor_queue'), 'icon' => 'list-numbers', 'primary' => true];
        }
        if (! $front_desk && $user->can('prescription.dispense')) {
            $d['actions'][] = ['url' => action([\App\Http\Controllers\ClinicDispensaryController::class, 'index']), 'label' => __('clinic.dispensary'), 'icon' => 'pill', 'primary' => true];
        }
        if (! $front_desk && $user->can('lab_request.view')) {
            $d['actions'][] = ['url' => action([\App\Http\Controllers\LabRequestController::class, 'index']), 'label' => __('clinic.menu_laboratory'), 'icon' => 'microscope', 'primary' => empty($d['actions'])];
        }
        if ($user->can('patient.view_bill')) {
            $d['actions'][] = ['url' => action([\App\Http\Controllers\ClinicAccountController::class, 'index']), 'label' => __('clinic.dash_take_payment'), 'icon' => 'cash', 'primary' => empty($d['actions'])];
        }

        // ---------- today's patients (waiting room)
        // the waiting room is for the front desk, the doctors and the cashier (pay-first clinics)
        $sees_all = $user->can('visit.view') || $user->can('visit.create') || $user->can('patient.view_bill');
        if ($sees_all || $user->can('visit.receive_patients')) {
            $q = Visit::forBusiness($business_id)->whereDate('visit_date', $today->toDateString())->whereNotNull('queue_no');
            // a doctor who cannot see the whole clinic sees only their own patients
            $mine_only = ! $sees_all;
            if ($mine_only) {
                $q->where('doctor_id', $user->id);
            }
            $visits = $q->with(['patient.patientDetails', 'doctor', 'appointment', 'feeTransaction'])->orderBy('queue_no')->get();
            $waiting = $visits->filter(function ($v) {
                return $v->status == 'open' && $v->queue_status == 'waiting';
            });
            $with_doctor = $visits->filter(function ($v) {
                return $v->status == 'open' && $v->queue_status == 'with_doctor';
            });
            $queue_url = $user->can('visit.receive_patients')
                ? action([\App\Http\Controllers\ClinicQueueController::class, 'index'])
                : action([\App\Http\Controllers\VisitController::class, 'index']);

            // a doctor (not working the front desk) counts their own patients
            $personal = $mine_only || ($is_doctor && ! $user->can('visit.create'));
            $d['tiles'][] = ['key' => 'waiting', 'icon' => 'list-numbers', 'tone' => 'amber', 'url' => $queue_url,
                'label' => $personal ? __('clinic.dash_waiting_for_me') : __('clinic.dash_waiting'),
                'value' => $personal ? $waiting->where('doctor_id', $user->id)->count() : $waiting->count(),
                'sub' => $with_doctor->count() ? __('clinic.dash_with_doctor_n', ['n' => $with_doctor->count()]) : null];
            $counted = $personal ? $visits->where('doctor_id', $user->id) : $visits;
            $d['tiles'][] = ['key' => 'seen', 'icon' => 'stethoscope', 'tone' => 'green', 'url' => $queue_url,
                'label' => __('clinic.dash_seen_today'), 'value' => $counted->where('queue_status', 'seen')->count(),
                'sub' => trans_choice('clinic.dash_checked_in_n', $n_in = $counted->whereNotIn('queue_status', ['cancelled'])->count(), ['n' => $n_in])];

            // a doctor's list holds only their patients; otherwise everybody, the user's own first
            $active = $waiting->merge($with_doctor)->filter(function ($v) use ($personal, $user) {
                return ! $personal || $v->doctor_id == $user->id;
            })->sortBy(function ($v) use ($user) {
                return ($v->doctor_id == $user->id ? '0' : '1').($v->queue_status == 'with_doctor' ? '0' : '1').sprintf('%06d', $v->queue_no);
            })->values();
            $d['panels']['queue'] = [
                'title' => $personal ? __('clinic.dash_my_queue') : __('clinic.dash_waiting_room'),
                'url' => $queue_url,
                'rows' => $active->take(self::LIST_SIZE)->map(function ($v) use ($user, $queue_url) {
                    $unpaid = ClinicQueue::payGateBlocks($v);

                    return [
                        // the cashier goes straight to the account of a patient who still has to pay
                        'url' => $unpaid && $user->can('patient.view_bill')
                            ? action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$v->contact_id])
                            // the front desk opens the visit itself (cancel a wrong check-in there); a doctor, the queue
                            : ($user->can('visit.create') ? action([\App\Http\Controllers\VisitController::class, 'show'], [$v->id]) : $queue_url),
                        'no' => $v->queue_no,
                        'name' => optional($v->patient)->name,
                        'file' => optional(optional($v->patient)->patientDetails)->file_number,
                        'doctor' => $v->doctor ? trim($v->doctor->user_full_name) : null,
                        'mine' => $v->doctor_id == $user->id,
                        'status' => $v->queue_status,
                        'since' => $v->checked_in_at ? Carbon::parse($v->checked_in_at) : null,
                        'booked' => $v->appointment ? substr($v->appointment->start_time, 0, 5) : null,
                        'unpaid' => $unpaid,
                        // the front desk takes vitals after payment: flag who still needs them
                        'no_vitals' => ! $v->hasVitals() && $v->queue_status == 'waiting' && ! $unpaid && $user->can('visit.record_vitals'),
                    ];
                })->all(),
                'more' => max(0, $active->count() - self::LIST_SIZE),
            ];
        }

        // ---------- appointments today
        $appt_access = $appts_on ? ClinicAppointments::access($business_id, $user) : null;
        if ($appts_on && $appt_access['see']) {
            // a doctor away from the front desk (or who sees only their own diary) sees their own bookings,
            // like their waiting-room counts
            $appts_mine = $appt_access['own'] !== null && (! $appt_access['see_all'] || $appt_access['mine_first']);
            $mine = function ($q) use ($appts_mine, $user) {
                return $appts_mine ? $q->where('doctor_id', $user->id) : $q;
            };
            $appts = $mine(ClinicAppointment::forBusiness($business_id)->whereDate('date', $today->toDateString()))
                ->whereIn('status', ['requested', 'confirmed', 'checked_in'])
                ->with(['patient', 'doctor'])->orderBy('start_time')->get();
            $to_confirm = $mine(ClinicAppointment::forBusiness($business_id)->where('status', 'requested'))
                ->whereDate('date', '>=', $today->toDateString())->count();
            $url = action([\App\Http\Controllers\ClinicAppointmentController::class, $appts_mine ? 'upcoming' : 'index']);
            $title = $appts_mine ? __('clinic.dash_my_appointments_today') : __('clinic.dash_appointments_today');
            $d['tiles'][] = ['key' => 'appointments', 'icon' => 'calendar-event', 'tone' => 'blue', 'url' => $url,
                'label' => $title, 'value' => $appts->count(),
                'sub' => $to_confirm ? __('clinic.dash_to_confirm_n', ['n' => $to_confirm]) : null, 'alert' => $to_confirm > 0];
            $now = Carbon::now()->format('H:i:s');
            $d['panels']['appointments'] = [
                'title' => $title,
                'url' => $url,
                'rows' => $appts->sortBy(function ($a) {
                    // still to come first, then the ones already checked in
                    return ($a->status == 'checked_in' ? '1' : '0').$a->start_time;
                })->take(self::LIST_SIZE)->map(function ($a) use ($now) {
                    return [
                        'time' => substr($a->start_time, 0, 5),
                        'name' => optional($a->patient)->name,
                        'doctor' => $a->doctor ? trim($a->doctor->user_full_name) : null,
                        'status' => $a->status,
                        'late' => $a->status == 'confirmed' && $a->start_time < $now,
                    ];
                })->values()->all(),
                'more' => max(0, $appts->count() - self::LIST_SIZE),
                'to_confirm' => $to_confirm,
            ];
        }

        // ---------- laboratory
        if ($user->can('lab_request.view')) {
            $lab = LabRequest::forBusiness($business_id)->whereIn('lab_requests.status', ['pending', 'sample_collected', 'processing', 'resulted']);
            $counts = (clone $lab)->select('lab_requests.status', DB::raw('COUNT(*) as n'))->groupBy('lab_requests.status')->pluck('n', 'status');
            $in_lab = (int) ($counts['pending'] ?? 0) + (int) ($counts['sample_collected'] ?? 0) + (int) ($counts['processing'] ?? 0);
            $to_validate = (int) ($counts['resulted'] ?? 0);
            $url = action([\App\Http\Controllers\LabRequestController::class, 'index']);
            $d['tiles'][] = ['key' => 'lab', 'icon' => 'microscope', 'tone' => 'violet', 'url' => $url,
                'label' => __('clinic.dash_lab_waiting'), 'value' => $in_lab,
                'sub' => $to_validate ? __('clinic.dash_to_validate_n', ['n' => $to_validate]) : null];
            $rows = (clone $lab)->leftJoin('contacts as c', 'c.id', '=', 'lab_requests.contact_id')
                ->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'lab_requests.contact_id')
                ->select('lab_requests.id', 'lab_requests.ref_no', 'lab_requests.status', 'lab_requests.requested_at', 'c.name', 'pd.file_number',
                    DB::raw('(SELECT COUNT(*) FROM lab_request_lines l WHERE l.lab_request_id = lab_requests.id) as tests'))
                ->orderBy('lab_requests.requested_at')->limit(self::LIST_SIZE)->get();
            $d['panels']['lab'] = [
                'title' => __('clinic.dash_lab_queue'),
                'url' => $url,
                'rows' => $rows->map(function ($r) {
                    return ['id' => $r->id, 'ref' => $r->ref_no, 'name' => $r->name, 'file' => $r->file_number, 'tests' => (int) $r->tests,
                        'status' => $r->status, 'since' => $r->requested_at ? Carbon::parse($r->requested_at) : null,
                        'url' => action([\App\Http\Controllers\LabRequestController::class, 'show'], [$r->id])];
                })->all(),
                'more' => max(0, $in_lab + $to_validate - self::LIST_SIZE),
            ];
        }

        // ---------- dispensary (same rule as the dispensary queue: last 30 days, something still to give)
        if ($user->can('prescription.dispense')) {
            $waiting_sql = Dispensing::waitingCountSql();
            $rx = Consultation::forBusiness($business_id)->where('consultations.status', 'final')
                ->where('consultations.updated_at', '>=', now()->subDays(30))
                ->whereRaw($waiting_sql.' > 0');
            $n = (clone $rx)->count();
            $url = action([\App\Http\Controllers\ClinicDispensaryController::class, 'index']);
            $d['tiles'][] = ['key' => 'dispensary', 'icon' => 'pill', 'tone' => 'teal', 'url' => $url,
                'label' => __('clinic.dash_rx_waiting'), 'value' => $n];
            $rows = (clone $rx)->join('contacts as c', 'c.id', '=', 'consultations.contact_id')
                ->leftJoin('users as u', 'u.id', '=', 'consultations.consulted_by')
                ->select('consultations.id', 'consultations.updated_at', 'c.name',
                    DB::raw("TRIM(CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as doctor"),
                    DB::raw($waiting_sql.' as waiting'))
                ->orderBy('consultations.updated_at')->limit(self::LIST_SIZE)->get();
            $d['panels']['dispensary'] = [
                'title' => __('clinic.dash_rx_queue'),
                'url' => $url,
                'rows' => $rows->map(function ($r) {
                    return ['name' => $r->name, 'doctor' => $r->doctor, 'lines' => (int) $r->waiting,
                        'since' => Carbon::parse($r->updated_at),
                        'url' => action([\App\Http\Controllers\ClinicDispensaryController::class, 'show'], [$r->id])];
                })->all(),
                'more' => max(0, $n - self::LIST_SIZE),
            ];
        }

        // ---------- billing (same sums as Patient accounts)
        if ($user->can('patient.view_bill')) {
            $sells = "t.contact_id = c.id AND t.type = 'sell' AND t.status = 'final'";
            $owing = DB::table('contacts as c')->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'c.id')
                ->where('c.business_id', $business_id)->where('c.is_patient', 1)->whereNull('c.deleted_at')
                ->select('c.id', 'c.name', 'pd.file_number',
                    DB::raw("(SELECT COALESCE(SUM(t.final_total), 0) FROM transactions t WHERE $sells) as invoiced"),
                    DB::raw("(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp JOIN transactions t ON t.id = tp.transaction_id WHERE $sells) as paid"))
                ->havingRaw('invoiced - paid > 0.004')->orderByRaw('invoiced - paid DESC')->limit(300)->get();
            $received = DB::table('transaction_payments as tp')->join('transactions as t', 't.id', '=', 'tp.transaction_id')
                ->join('contacts as c', 'c.id', '=', 't.contact_id')
                ->where('t.business_id', $business_id)->where('t.type', 'sell')->where('t.status', 'final')->where('c.is_patient', 1)
                ->whereDate('tp.paid_on', $today->toDateString())
                ->sum(DB::raw('IF(tp.is_return = 1, -1 * tp.amount, tp.amount)'));
            $url = action([\App\Http\Controllers\ClinicAccountController::class, 'index']);
            $d['tiles'][] = ['key' => 'received', 'icon' => 'cash', 'tone' => 'green', 'url' => $url,
                'label' => __('clinic.dash_received_today'), 'money' => (float) $received];
            $d['tiles'][] = ['key' => 'owing', 'icon' => 'receipt', 'tone' => 'red', 'url' => $url,
                'label' => __('clinic.dash_unpaid_bills'), 'money' => (float) $owing->sum(function ($p) {
                    return (float) $p->invoiced - (float) $p->paid;
                }),
                'sub' => $owing->count() ? trans_choice('clinic.dash_patients_owing', $owing->count(), ['n' => $owing->count()]) : null];
            $d['panels']['bills'] = [
                'title' => __('clinic.dash_unpaid_bills'),
                'url' => $url,
                'rows' => $owing->take(self::LIST_SIZE)->map(function ($p) {
                    return ['name' => $p->name, 'file' => $p->file_number, 'balance' => round((float) $p->invoiced - (float) $p->paid, 4),
                        'url' => action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$p->id])];
                })->values()->all(),
                'more' => max(0, $owing->count() - self::LIST_SIZE),
            ];
        }

        // ---------- patients & beds
        if ($user->can('patient.view')) {
            $new = DB::table('contacts')->where('business_id', $business_id)->where('is_patient', 1)->whereNull('deleted_at')
                ->whereDate('created_at', $today->toDateString())->count();
            $total = DB::table('contacts')->where('business_id', $business_id)->where('is_patient', 1)->whereNull('deleted_at')->count();
            $d['tiles'][] = ['key' => 'patients', 'icon' => 'users', 'tone' => 'slate', 'url' => action([\App\Http\Controllers\PatientController::class, 'index']),
                'label' => __('clinic.dash_new_patients'), 'value' => $new, 'sub' => __('clinic.dash_patients_total_n', ['n' => number_format($total)])];
        }
        if ($on('wards') && $user->can('admission.view')) {
            $beds = DB::table('beds')->where('business_id', $business_id);
            $total_beds = (clone $beds)->count();
            $occupied = (clone $beds)->where('status', 'occupied')->count();
            $d['tiles'][] = ['key' => 'inpatients', 'icon' => 'bed', 'tone' => 'blue', 'url' => action([\App\Http\Controllers\AdmissionController::class, 'index']),
                'label' => __('clinic.dash_inpatients'), 'value' => Admission::forBusiness($business_id)->active()->count(),
                'sub' => $total_beds ? __('clinic.dash_beds_free_n', ['n' => $total_beds - $occupied, 'total' => $total_beds]) : null];
        }

        return $d;
    }
}
