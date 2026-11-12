<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A WhatsApp message sent to or received from a patient, with its delivery
 * status from Meta's webhook. See App\Utils\ClinicWhatsApp.
 */
class ClinicWhatsappMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    /** Delivery steps in order: a late "sent" never undoes "read". */
    const RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function appointment()
    {
        return $this->belongsTo(ClinicAppointment::class, 'appointment_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_whatsapp_messages.business_id', $business_id);
    }

    /** Patient replies not yet dealt with. */
    public function scopeUnhandled($query)
    {
        return $query->where('direction', 'in')->where('status', 'received');
    }
}
