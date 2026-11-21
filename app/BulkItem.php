<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova: a product opened and used a little at a time (App\Utils\BulkItems). */
class BulkItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'container_size' => 'float'];

    const LABELS = ['bag', 'pack', 'bottle', 'tin', 'carton', 'sack', 'box', 'jar', 'cylinder'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(Variation::class);
    }

    public function cycles()
    {
        return $this->hasMany(BulkItemCycle::class);
    }

    public function name()
    {
        $p = $this->product;
        $v = $this->variation;
        if (! $p) {
            return '#' . $this->id;
        }

        return $p->name . ($p->type === 'variable' && $v && $v->name && strtoupper($v->name) !== 'DUMMY' ? ' - ' . $v->name : '');
    }
}
