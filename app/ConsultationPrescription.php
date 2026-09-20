<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A prescription line on a consultation (hospital module).
 */
class ConsultationPrescription extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_dispensed' => 'boolean',
        'dispensed_at' => 'datetime',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variation()
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }
}
