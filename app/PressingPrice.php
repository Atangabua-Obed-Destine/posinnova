<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova pressing: the price of one service for one type of item (guest_price: hotel guests, when set). */
class PressingPrice extends Model
{
    protected $guarded = ['id'];
}
