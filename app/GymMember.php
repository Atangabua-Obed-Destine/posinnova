<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova gym: a member (a contact with a member number, shown on the card's QR code). */
class GymMember extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['whatsapp' => 'boolean'];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function memberships()
    {
        return $this->hasMany(GymMembership::class, 'member_id')->orderByDesc('end_date')->orderByDesc('id');
    }

    public function visits()
    {
        return $this->hasMany(GymVisit::class, 'member_id')->orderByDesc('visited_at');
    }

    /** The membership that lets in today, if any (a period covering today, or visits left). */
    public function current($date = null)
    {
        $date = $date ?: now()->toDateString();

        return GymMembership::where('member_id', $this->id)->where('start_date', '<=', $date)->where('end_date', '>=', $date)
            ->where(fn ($q) => $q->whereNull('visits_left')->orWhere('visits_left', '>', 0))
            ->orderBy('end_date')->first();
    }

    /** The membership that ends last (valid, future or lapsed). */
    public function latest()
    {
        return GymMembership::where('member_id', $this->id)->orderByDesc('end_date')->orderByDesc('id')->first();
    }
}
