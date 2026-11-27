<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One WhatsApp booking-bot conversation in progress (see App\Utils\ClinicWhatsAppBot).
 * Short-lived state keyed by business + number; cleared when the chat ends or expires.
 */
class ClinicWaSession extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('business_id', $business_id);
    }

    /** The live session for a number, if any (not expired). */
    public static function current($business_id, $wa_number)
    {
        $s = static::forBusiness($business_id)->where('wa_number', $wa_number)->first();
        if ($s && $s->expires_at && $s->expires_at->isPast()) {
            $s->delete();

            return null;
        }

        return $s;
    }
}
