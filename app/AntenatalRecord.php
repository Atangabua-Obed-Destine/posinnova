<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A pregnancy record (hospital module, maternity).
 */
class AntenatalRecord extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'lmp' => 'date',
        'edd' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function mother()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function antenatalVisits()
    {
        return $this->hasMany(AntenatalVisit::class, 'antenatal_record_id');
    }

    public function delivery()
    {
        return $this->hasOne(DeliveryRecord::class, 'antenatal_record_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('antenatal_records.business_id', $business_id);
    }

    /**
     * Estimated due date from LMP (Naegele's rule: LMP + 280 days).
     */
    public static function eddFromLmp($lmp)
    {
        if (empty($lmp)) {
            return null;
        }

        return \Carbon::parse($lmp)->addDays(280)->toDateString();
    }

    /**
     * Current gestational age in weeks from LMP (null when no LMP).
     */
    public function getGestationalAgeWeeksAttribute()
    {
        if (empty($this->lmp)) {
            return null;
        }

        return (int) floor($this->lmp->diffInDays(now()) / 7);
    }
}
