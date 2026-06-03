<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-variation per-unit selling price override.
 * Created by PosInnova for pack-size pricing (e.g. Bottle / Carton-12 / Carton-24).
 */
class VariationUnitPrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price_exc_tax' => 'float',
        'price_inc_tax' => 'float',
    ];

    public function variation()
    {
        return $this->belongsTo(\App\Variation::class, 'variation_id');
    }

    public function unit()
    {
        return $this->belongsTo(\App\Unit::class, 'unit_id');
    }
}
