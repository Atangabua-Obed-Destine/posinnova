<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An imaging request + its radiologist report (hospital module).
 */
class ImagingRequest extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'requested_at' => 'datetime',
        'reported_at' => 'datetime',
        'fee' => 'decimal:4',
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

    public function catalogue()
    {
        return $this->belongsTo(ImagingCatalogue::class, 'imaging_catalogue_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    /** PosInnova: photos / scans / PDFs of the result (see App\ClinicAttachment). */
    public function attachments()
    {
        return $this->hasMany(ClinicAttachment::class, 'attachable_id')->where('attachable_type', 'imaging_request')->orderBy('id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('imaging_requests.business_id', $business_id);
    }
}
