<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A patient encounter (hospital module). Container for consultations, lab
 * requests, pharmacy dispenses and admissions during one attendance.
 */
class Visit extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'visit_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'visit_id');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('visits.business_id', $business_id);
    }

    public function scopeOpen($query)
    {
        return $query->where('visits.status', 'open');
    }
}
