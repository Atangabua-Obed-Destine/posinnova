<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova pressing: a lost or damaged piece, or a complaint, and what was agreed. */
class PressingProblem extends Model
{
    const KINDS = ['lost', 'damaged', 'complaint'];

    const OUTCOMES = ['refund', 'rewash', 'none'];

    protected $guarded = ['id'];

    public function piece()
    {
        return $this->belongsTo(PressingPiece::class, 'piece_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
