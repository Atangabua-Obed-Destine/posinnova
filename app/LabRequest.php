<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A laboratory request (hospital module). Holds one or more test lines.
 */
class LabRequest extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'requested_at' => 'datetime',
        'resulted_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function lines()
    {
        return $this->hasMany(LabRequestLine::class, 'lab_request_id');
    }

    /** PosInnova: photos / scans / PDFs of the result (see App\ClinicAttachment). */
    public function attachments()
    {
        return $this->hasMany(ClinicAttachment::class, 'attachable_id')->where('attachable_type', 'lab_request')->orderBy('id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('lab_requests.business_id', $business_id);
    }

    /**
     * Total fee of all requested tests.
     */
    public function getTotalFeeAttribute()
    {
        return $this->lines->sum('fee');
    }
}
