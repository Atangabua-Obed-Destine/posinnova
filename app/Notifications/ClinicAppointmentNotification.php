<?php

namespace App\Notifications;

use App\ClinicAppointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a doctor (in the header bell and on My practice) that something happened
 * to one of his appointments: booked/confirmed for him, moved, moved to another
 * doctor, cancelled, or a request waiting for him to approve.
 * Sent by App\Utils\ClinicAppointments::tellDoctor().
 */
class ClinicAppointmentNotification extends Notification
{
    use Queueable;

    const KINDS = ['booked', 'moved', 'moved_away', 'cancelled', 'request'];

    public $data;

    public function __construct(ClinicAppointment $appt, $kind)
    {
        $this->data = [
            'appointment_id' => $appt->id,
            'business_id' => $appt->business_id,
            'kind' => in_array($kind, self::KINDS, true) ? $kind : 'booked',
            'patient' => $appt->display_name,
            'date' => $appt->date->toDateString(),
            'time' => $appt->start,
        ];
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return $this->data;
    }

    /** The sentence shown to the doctor, in the current language. */
    public static function message(array $data)
    {
        $date = ! empty($data['date']) ? Carbon::parse($data['date'])->locale(app()->getLocale())->isoFormat('ddd D MMM') : '';

        return __('clinic.dn_'.($data['kind'] ?? 'booked'), [
            'name' => $data['patient'] ?? '',
            'date' => $date,
            'time' => $data['time'] ?? '',
        ]);
    }

    /** The Font Awesome icon (with the bell's colour class) for a kind. */
    public static function icon($kind)
    {
        return [
            'booked' => 'fas fa-calendar-check bg-green',
            'moved' => 'fas fa-exchange-alt bg-yellow',
            'moved_away' => 'fas fa-exchange-alt bg-gray',
            'cancelled' => 'fas fa-calendar-times bg-red',
            'request' => 'fas fa-calendar-plus bg-aqua',
        ][$kind] ?? 'fas fa-calendar bg-green';
    }
}
