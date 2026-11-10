<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A delivery record (hospital module, maternity).
 */
class DeliveryRecord extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'delivery_date' => 'datetime',
        'baby_weight' => 'decimal:2',
        'fee' => 'decimal:4',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function mother()
    {
        return $this->belongsTo(Contact::class, 'mother_contact_id');
    }

    public function baby()
    {
        return $this->belongsTo(Contact::class, 'baby_contact_id');
    }

    public function antenatalRecord()
    {
        return $this->belongsTo(AntenatalRecord::class, 'antenatal_record_id');
    }

    public function attendedBy()
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('delivery_records.business_id', $business_id);
    }
}
