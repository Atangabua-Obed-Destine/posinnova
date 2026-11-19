<?php

namespace App\Observers;

use App\Brands;
use App\Category;
use App\Product;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Unit;
use App\Variation;
use App\VariationGroupPrice;
use App\VariationUnitPrice;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * PosInnova: the audit trail of products and prices. Every change to a product, a variation's
 * prices, a selling-price-group price or a per-unit price is written to the activity log, whatever
 * screen made it (product form, bulk price update, import, inventory count, purchase...).
 *
 * One request = one line per product, listing each change as "field: before -> after". The log is
 * written in the same database transaction as the change, so a change that is rolled back leaves
 * no trace. Names (category, unit, tax, price group) are stored as they were at the time.
 */
class ProductChangeObserver
{
    /** product fields followed => how to show their value */
    const PRODUCT_FIELDS = [
        'name' => 'text', 'sku' => 'text', 'unit_id' => 'unit', 'category_id' => 'category', 'sub_category_id' => 'category',
        'brand_id' => 'brand', 'tax' => 'tax', 'alert_quantity' => 'number', 'enable_stock' => 'bool', 'is_inactive' => 'bool',
        'not_for_selling' => 'bool', 'expiry_period' => 'number', 'expiry_period_type' => 'text', 'type' => 'text',
    ];

    const VARIATION_FIELDS = [
        'name' => 'text', 'sub_sku' => 'text', 'default_purchase_price' => 'money', 'dpp_inc_tax' => 'money',
        'default_sell_price' => 'money', 'sell_price_inc_tax' => 'money',
    ];

    const MAX_CHANGES = 300;

    /** product id => the activity line of this request */
    protected static $open = [];

    /** Switched off while seeding / migrating data (e.g. imports run by the developers). */
    public static $enabled = true;

    public function created(Model $m)
    {
        $this->handle($m, 'created');
    }

    public function updated(Model $m)
    {
        $this->handle($m, 'updated');
    }

    public function deleted(Model $m)
    {
        $this->handle($m, 'deleted');
    }

    protected function handle(Model $m, $event)
    {
        if (! self::$enabled) {
            return;
        }
        try {
            if ($m instanceof Product) {
                $this->product($m, $event);
            } elseif ($m instanceof Variation) {
                $this->variation($m, $event);
            } elseif ($m instanceof VariationGroupPrice) {
                $this->groupPrice($m, $event);
            } elseif ($m instanceof VariationUnitPrice) {
                $this->unitPrice($m, $event);
            }
        } catch (\Throwable $e) {
            // the audit trail must never stop a sale or a price change
            \Illuminate\Support\Facades\Log::warning('product activity log: ' . $e->getMessage());
        }
    }

    protected function product(Product $p, $event)
    {
        if ($event === 'created') {
            $this->record($p, 'product_added', []);

            return;
        }
        if ($event === 'deleted') {
            $this->record($p, 'product_deleted', []);

            return;
        }
        $changes = [];
        foreach (self::PRODUCT_FIELDS as $f => $kind) {
            if (! $p->wasChanged($f)) {
                continue;
            }
            $from = $p->getOriginal($f);
            $to = $p->getAttribute($f);
            if ($this->same($from, $to, $kind)) {
                continue;
            }
            $changes[] = ['f' => 'p.' . $f, 'from' => $this->show($from, $kind), 'to' => $this->show($to, $kind)];
        }
        if ($changes) {
            $this->record($p, 'product_edited', $changes);
        }
    }

    protected function variation(Variation $v, $event)
    {
        $p = $this->productOf($v->product_id);
        if (! $p) {
            return;
        }
        $label = $this->variationLabel($v);
        if ($event === 'created') {
            // a new product's own variations: their first prices, on the "added" line
            $this->record($p, 'product_edited', [[
                'f' => 'v.added', 'v' => $label,
                'to' => trim(($v->sub_sku ?: '') . ' · ' . $this->show($v->sell_price_inc_tax, 'money'), ' ·'),
                'buy' => $this->show($v->default_purchase_price, 'money'),
            ]]);

            return;
        }
        if ($event === 'deleted') {
            $this->record($p, 'product_edited', [['f' => 'v.removed', 'v' => $label, 'from' => $v->sub_sku]]);

            return;
        }
        $changes = [];
        foreach (self::VARIATION_FIELDS as $f => $kind) {
            if (! $v->wasChanged($f)) {
                continue;
            }
            $from = $v->getOriginal($f);
            $to = $v->getAttribute($f);
            if ($this->same($from, $to, $kind)) {
                continue;
            }
            // without tax both pairs are equal: keep one of each
            if ($f === 'dpp_inc_tax' && $this->sameChange($v, 'default_purchase_price', $from, $to)) {
                continue;
            }
            if ($f === 'default_sell_price' && $this->sameChange($v, 'sell_price_inc_tax', $from, $to)) {
                continue;
            }
            $changes[] = ['f' => 'v.' . $f, 'v' => $label, 'from' => $this->show($from, $kind), 'to' => $this->show($to, $kind)];
        }
        if ($changes) {
            $this->record($p, 'product_edited', $changes);
        }
    }

    protected function groupPrice(VariationGroupPrice $g, $event)
    {
        $v = Variation::withTrashed()->find($g->variation_id);
        $p = $v ? $this->productOf($v->product_id) : null;
        if (! $p) {
            return;
        }
        $group = optional(SellingPriceGroup::find($g->price_group_id))->name ?: ('#' . $g->price_group_id);
        $kind = ($g->price_type ?? 'fixed') === 'percentage' ? 'percent' : 'money';
        $from = $event === 'created' ? null : $g->getOriginal('price_inc_tax');
        $to = $event === 'deleted' ? null : $g->price_inc_tax;
        if ($event === 'updated' && (! $g->wasChanged('price_inc_tax') || $this->same($from, $to, 'money'))) {
            return;
        }
        $this->record($p, 'product_edited', [[
            'f' => 'g.price', 'v' => $this->variationLabel($v), 'g' => $group,
            'from' => $from === null ? null : $this->show($from, $kind), 'to' => $to === null ? null : $this->show($to, $kind),
        ]]);
    }

    protected function unitPrice(VariationUnitPrice $u, $event)
    {
        $v = Variation::withTrashed()->find($u->variation_id);
        $p = $v ? $this->productOf($v->product_id) : null;
        if (! $p) {
            return;
        }
        $from = $event === 'created' ? null : $u->getOriginal('price_inc_tax');
        $to = $event === 'deleted' ? null : $u->price_inc_tax;
        if ($event === 'updated' && (! $u->wasChanged('price_inc_tax') || $this->same($from, $to, 'money'))) {
            return;
        }
        $this->record($p, 'product_edited', [[
            'f' => 'u.price', 'v' => $this->variationLabel($v), 'u' => $this->show($u->unit_id, 'unit'),
            'from' => $from === null ? null : $this->show($from, 'money'), 'to' => $to === null ? null : $this->show($to, 'money'),
        ]]);
    }

    /** One activity line per product and request; later changes are added to it. */
    protected function record(Product $p, $action, array $changes)
    {
        $rank = ['product_edited' => 1, 'product_added' => 2, 'product_deleted' => 3];
        // read back from the database: a rolled-back change (or line) must not come back
        $a = isset(self::$open[$p->id]) ? Activity::find(self::$open[$p->id]) : null;
        if ($a) {
            $props = $a->properties ? $a->properties->toArray() : [];
            $props['changes'] = array_slice(array_merge($props['changes'] ?? [], $changes), 0, self::MAX_CHANGES);
            $props['name'] = $p->name;
            $props['sku'] = $p->sku;
            $a->properties = $props;
            if (($rank[$action] ?? 0) > ($rank[$a->description] ?? 0)) {
                $a->description = $action;
            }
            $a->save();

            return;
        }
        $req = app()->runningInConsole() ? null : request();
        $a = activity()
            ->performedOn($p)
            ->withProperties([
                'name' => $p->name,
                'sku' => $p->sku,
                'changes' => array_slice($changes, 0, self::MAX_CHANGES),
                'via' => $req ? $req->path() : 'console',
            ])
            ->log($action);
        $a->business_id = $p->business_id;
        $a->save();
        self::$open[$p->id] = $a->id;
    }

    protected function productOf($product_id)
    {
        return $product_id ? Product::find($product_id) : null;
    }

    protected function variationLabel(?Variation $v)
    {
        if (! $v) {
            return null;
        }
        $name = trim((string) $v->name);

        return ($name === '' || strtoupper($name) === 'DUMMY') ? null : $name;
    }

    protected function sameChange(Variation $v, $other, $from, $to)
    {
        return $v->wasChanged($other) && $this->same($v->getOriginal($other), $from, 'money') && $this->same($v->getAttribute($other), $to, 'money');
    }

    protected function same($a, $b, $kind)
    {
        if (in_array($kind, ['money', 'number', 'percent'])) {
            if (($a === null || $a === '') && ($b === null || $b === '')) {
                return true;
            }

            return is_numeric($a) && is_numeric($b) && abs((float) $a - (float) $b) < 0.00005;
        }
        if ($kind === 'bool') {
            return (bool) $a === (bool) $b;
        }

        return (string) $a === (string) $b;
    }

    protected function show($value, $kind)
    {
        if ($value === null || $value === '') {
            return null;
        }
        switch ($kind) {
            case 'money':
            case 'number':
            case 'percent':
                $n = (float) $value;
                $s = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');

                return $kind === 'percent' ? $s . '%' : $s;
            case 'bool':
                return (bool) $value ? 'yes' : 'no';
            case 'unit':
                $unit = Unit::find($value);
                $name = $unit ? trim((string) $unit->actual_name) : '';

                return $name !== '' ? $name : ($unit && trim((string) $unit->short_name, ' .') !== '' ? $unit->short_name : '#' . $value);
            case 'category':
                return optional(Category::find($value))->name ?: '#' . $value;
            case 'brand':
                return optional(Brands::find($value))->name ?: '#' . $value;
            case 'tax':
                $t = TaxRate::find($value);

                return $t ? $t->name . ' (' . rtrim(rtrim(number_format((float) $t->amount, 2, '.', ''), '0'), '.') . '%)' : '#' . $value;
        }

        return (string) $value;
    }
}
