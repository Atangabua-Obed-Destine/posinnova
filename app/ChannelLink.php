<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova online channels: one selling location linked to E-Ride (contract v1). */
class ChannelLink extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'full_requested' => 'boolean',
        'linked_at' => 'datetime', 'revoked_at' => 'datetime', 'last_ok_at' => 'datetime',
        'last_error_at' => 'datetime', 'last_heartbeat_at' => 'datetime', 'last_push_at' => 'datetime',
        'last_check_at' => 'datetime', 'last_hourly_at' => 'datetime', 'last_full_at' => 'datetime',
        'last_full_on' => 'date',
    ];

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    /** Linked, not revoked by E-Ride, not switched off here. */
    public function scopeLive($q)
    {
        return $q->where('is_active', 1)->whereNull('revoked_at');
    }

    /** The shop channel (products, stock, orders). */
    public function scopeShop($q)
    {
        return $q->where('channel', 'eride');
    }

    public function isLive()
    {
        return $this->is_active && empty($this->revoked_at);
    }
}
