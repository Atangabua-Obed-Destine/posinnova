<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The clinic's connection to Meta's WhatsApp Business Platform (one per
 * business). The access token and the app secret are encrypted at rest and
 * never sent back to the browser. See App\Utils\ClinicWhatsApp.
 */
class ClinicWhatsappAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'app_secret'];

    protected $casts = [
        'enabled' => 'boolean',
        'send_confirmation' => 'boolean',
        'send_reminder' => 'boolean',
        'send_moved' => 'boolean',
        'reminder_buttons' => 'boolean',
        'access_token' => 'encrypted',
        'app_secret' => 'encrypted',
        'templates_status' => 'array',
        'connection_ok_at' => 'datetime',
        'registered_at' => 'datetime',
        'webhook_subscribed_at' => 'datetime',
        'webhook_verified_at' => 'datetime',
        'last_webhook_at' => 'datetime',
        'templates_checked_at' => 'datetime',
    ];

    /** The business's account, created (switched off) on first use. */
    public static function forBusiness($business_id)
    {
        $acc = static::firstOrCreate(['business_id' => $business_id], ['verify_token' => Str::random(32)]);

        // a new row: read it back so the database defaults (template names, what to send...) are there
        return $acc->wasRecentlyCreated ? $acc->refresh() : $acc;
    }

    /** Enough details to call Meta at all. */
    public function hasCredentials()
    {
        return ! empty($this->phone_number_id) && ! empty($this->access_token);
    }

    /** Switched on and able to send. */
    public function ready()
    {
        return $this->enabled && $this->hasCredentials();
    }

    /** Template names by kind of message. */
    public function templateFor($kind)
    {
        return ['confirmation' => $this->tpl_confirmation, 'reminder' => $this->tpl_reminder, 'moved' => $this->tpl_moved][$kind] ?? null;
    }

    /** Is the kind of message switched on? */
    public function sends($kind)
    {
        return ['confirmation' => $this->send_confirmation, 'reminder' => $this->send_reminder, 'moved' => $this->send_moved][$kind] ?? false;
    }

    /** Approval status Meta gave a template in a language (APPROVED, PENDING, REJECTED...), or null. */
    public function templateStatus($name, $lang)
    {
        return $this->templates_status[$name][$lang] ?? null;
    }
}
