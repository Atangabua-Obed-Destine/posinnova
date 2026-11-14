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

    /** Quantity the patient gets at another pharmacy (closed as out of stock / buy elsewhere). */
    public $elsewhere_qty = null;

    /**
     * Close reasons meaning "the patient buys the rest at another pharmacy". not_stocked: a medicine the
     * doctor typed (the clinic does not have it); prescribed_outside: the doctor ticked "buys outside".
     */
    const ELSEWHERE_REASONS = ['out_of_stock', 'bought_elsewhere', 'not_stocked', 'prescribed_outside'];

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

    /** Medicine name, with the variation when it is not the dummy one; the typed name for a medicine not in the system. */
    public function displayName()
    {
        if ($this->isFreeText()) {
            return (string) $this->drug_name;
        }
        $name = optional($this->product)->name ?: $this->drug_name;
        $variation = optional($this->variation)->name;

        return $variation && $variation != 'DUMMY' ? $name.' ('.$variation.')' : $name;
    }

    /** Typed by the doctor: not a product of the clinic, so never dispensed here. */
    public function isFreeText()
    {
        return empty($this->variation_id);
    }

    /** The reason key of a closed line ("out_of_stock: note" -> "out_of_stock"). */
    public function closeReasonKey()
    {
        return $this->close_reason ? explode(': ', $this->close_reason, 2)[0] : null;
    }

    public function isElsewhere()
    {
        return $this->closed_at && in_array($this->closeReasonKey(), self::ELSEWHERE_REASONS);
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
