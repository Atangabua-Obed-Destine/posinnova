<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** PosInnova hotel: one room. hk_status: dirty | clean | inspected. */
class HotelRoom extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'ooo_from' => 'date', 'ooo_to' => 'date', 'hk_at' => 'datetime'];

    public function type()
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    /** Out of order on that date (both ends included). */
    public function outOfOrderOn($date)
    {
        if (empty($this->ooo_from)) {
            return false;
        }
        $d = Carbon::parse($date)->startOfDay();

        return $d->gte($this->ooo_from->copy()->startOfDay()) && (empty($this->ooo_to) || $d->lte($this->ooo_to->copy()->startOfDay()));
    }

    /** Out of order on any night of [from, to). */
    public function outOfOrderBetween($from, $to)
    {
        if (empty($this->ooo_from)) {
            return false;
        }
        $last = Carbon::parse($to)->subDay();

        return $this->ooo_from->lte($last) && (empty($this->ooo_to) || $this->ooo_to->gte(Carbon::parse($from)));
    }

    public function isTidy()
    {
        return in_array($this->hk_status, ['clean', 'inspected'], true);
    }
}
