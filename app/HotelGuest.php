<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova hotel: a person staying in a room, with the police register (fiche de police) details. */
class HotelGuest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['dob' => 'date', 'id_issued_on' => 'date', 'is_primary' => 'boolean'];

    const FIELDS = ['surname', 'first_names', 'sex', 'dob', 'birth_place', 'nationality', 'profession', 'id_type',
        'id_number', 'id_issued_on', 'id_issued_at', 'phone', 'address', 'coming_from', 'going_to', 'purpose'];

    public function fullName()
    {
        return trim(mb_strtoupper($this->surname).' '.$this->first_names);
    }
}
