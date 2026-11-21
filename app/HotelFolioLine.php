<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova hotel: links an invoice or a payment to a stay. The invoice / payment
 * is the money; this row only groups it on the folio.
 * kind: night | charge | late_checkout | payment
 */
class HotelFolioLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['night_key' => 'date', 'voided_at' => 'datetime'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payment()
    {
        return $this->belongsTo(TransactionPayment::class, 'transaction_payment_id');
    }
}
