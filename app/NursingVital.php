<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A bedside vital-sign observation (hospital module, nursing).
 */
class NursingVital extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'observed_at' => 'datetime',
        'temperature' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * MEWS-style flag: is any vital outside a normal adult range? Used to
     * highlight rows that need attention. Conservative thresholds.
     */
    public function getIsAbnormalAttribute()
    {
        $t = $this->temperature;
        $p = $this->pulse;
        $r = $this->respiratory_rate;
        $s = $this->spo2;
        $sys = $this->bp_systolic;

        return (! is_null($t) && ($t < 35.0 || $t >= 38.0))
            || (! is_null($p) && ($p < 50 || $p > 110))
            || (! is_null($r) && ($r < 10 || $r > 24))
            || (! is_null($s) && $s < 92)
            || (! is_null($sys) && ($sys < 90 || $sys > 160));
    }
}
