<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryCountSession extends Model
{
    protected $table = 'inventory_count_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'scope_ids' => 'array',
    ];

    public function lines()
    {
        return $this->hasMany(InventoryCountSessionLine::class, 'session_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function shortageTransaction()
    {
        return $this->belongsTo(Transaction::class, 'shortage_transaction_id');
    }

    public function surplusTransaction()
    {
        return $this->belongsTo(Transaction::class, 'surplus_transaction_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
