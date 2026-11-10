<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A prescription line on a consultation (hospital module).
 */
class ConsultationPrescription extends Model
{
    protected $guarded = ['id'];

    /** Set by App\Utils\Dispensing::attach(); not stored. */
    public $given_qty = null;

    public $outstanding_qty = null;

    public $dispense_state = null;

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_dispensed' => 'boolean',
        'dispensed_at' => 'datetime',
        'legacy_given' => 'decimal:4',
        'closed_at' => 'datetime',
    ];

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Medicine name, with the variation when it is not the dummy one. */
    public function displayName()
    {
        $name = optional($this->product)->name;
        $variation = optional($this->variation)->name;

        return $variation && $variation != 'DUMMY' ? $name.' ('.$variation.')' : $name;
    }

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
