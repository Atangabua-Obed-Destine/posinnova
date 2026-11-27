<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A booked consultation slot (clinic appointments). requested = asked by the
 * patient from the portal, waiting for reception; confirmed = reception
 * booked or accepted it; checked_in = the patient arrived (visit_id);
 * cancelled / declined / no_show. See App\Utils\ClinicAppointments.
 */
class ClinicAppointment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminded_at' => 'datetime',
        'patient_reply_at' => 'datetime',
    ];

    /** WhatsApp messages about this appointment (sent automatically, and the patient's answers). */
    public function whatsappMessages()
    {
        return $this->hasMany(ClinicWhatsappMessage::class, 'appointment_id')->orderBy('id');
    }

    /** The latest automatic message sent about it, for the diary's delivery tick. */
    public function lastWhatsappOut()
    {
        return $this->relationLoaded('whatsappMessages')
            ? $this->whatsappMessages->where('direction', 'out')->sortByDesc('id')->first()
            : $this->whatsappMessages()->where('direction', 'out')->reorder()->orderByDesc('id')->first();
    }

    /** Statuses that hold the slot and still lie ahead. */
    const OPEN = ['requested', 'confirmed'];

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /** A public-page booking by someone who is not yet a patient (reception registers them on approval). */
    public function isGuest()
    {
        return empty($this->contact_id);
    }

    /** The name to show on the diary / alert: the patient's, or the guest's typed name. */
    public function getDisplayNameAttribute()
    {
        return $this->contact_id ? optional($this->patient)->name : ($this->guest_name ?: null);
    }

    /** The phone to show / message: the patient's mobile, or the guest's typed phone. */
    public function getDisplayMobileAttribute()
    {
        return $this->contact_id ? optional($this->patient)->mobile : ($this->guest_phone ?: null);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_appointments.business_id', $business_id);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('clinic_appointments.status', self::OPEN);
    }

    /** "09:20" */
    public function getStartAttribute()
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function getEndAttribute()
    {
        return substr((string) $this->end_time, 0, 5);
    }

    /** When the appointment starts, as a Carbon date-time. */
    public function startsAt()
    {
        return \Carbon\Carbon::parse($this->date->toDateString().' '.$this->start_time);
    }
}
