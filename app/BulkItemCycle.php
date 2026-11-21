<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova: one container of a bulk item, from opened to finished (App\Utils\BulkItems). */
class BulkItemCycle extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opened_at' => 'datetime', 'closed_at' => 'datetime', 'breakdown' => 'array',
        'containers' => 'float', 'expected_usage' => 'float', 'actual_usage' => 'float', 'yield_pct' => 'float',
        'unit_cost' => 'float', 'value' => 'float',
    ];

    public function item()
    {
        return $this->belongsTo(BulkItem::class, 'bulk_item_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
