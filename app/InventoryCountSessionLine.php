<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryCountSessionLine extends Model
{
    protected $table = 'inventory_count_session_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'counted_at' => 'datetime',
        'exp_date' => 'date',
        'counted_breakdown' => 'array',
    ];

    public function purchaseLine()
    {
        return $this->belongsTo(PurchaseLine::class, 'purchase_line_id');
    }

    public function session()
    {
        return $this->belongsTo(InventoryCountSession::class, 'session_id');
    }

    public function variation()
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
