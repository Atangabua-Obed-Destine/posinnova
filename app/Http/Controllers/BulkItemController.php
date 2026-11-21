<?php

namespace App\Http\Controllers;

use App\BulkItem;
use App\BulkItemCycle;
use App\BusinessLocation;
use App\Utils\BulkItems;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova: Stock > Bulk items (bags, packs, bottles opened and used a little at a time).
 * In use (with "bag finished"), history with the yield of every bag, set-up; and the POS
 * "Bags" button, which shows the same cards for the till's location.
 */
class BulkItemController extends Controller
{
    protected $bulk;

    protected $tu;

    public function __construct(ProductUtil $pu, TransactionUtil $tu)
    {
        $this->bulk = new BulkItems($pu, $tu);
        $this->tu = $tu;
    }

    // ------------------------------------------------------------------ who may do what

    public static function canView()
    {
        $u = auth()->user();

        return $u->can('sell.create') || $u->can('stock_adjustment.create') || $u->can('product.view') || $u->can('stock_report.view');
    }

    public static function canTap()
    {
        $u = auth()->user();

        return $u->can('sell.create') || $u->can('stock_adjustment.create');
    }

    public static function canSetup()
    {
        $u = auth()->user();

        return $u->can('product.update') || $u->can('product.create');
    }

    protected function businessId()
    {
        return request()->session()->get('user.business_id');
    }

    /** The locations this person works at, and the chosen one. */
    protected function locations(Request $request)
    {
        $locations = BusinessLocation::forDropdown($this->businessId())->toArray();
        $loc = (int) $request->input('location_id');
        if (! isset($locations[$loc])) {
            $loc = (int) array_key_first($locations);
        }

        return [$locations, $loc];
    }

    protected function item($id)
    {
        return BulkItem::where('business_id', $this->businessId())->with('product.unit', 'variation')->findOrFail($id);
    }

    // ------------------------------------------------------------------ pages

    public function index(Request $request)
    {
        abort_unless(self::canView(), 403, 'Unauthorized action.');
        [$locations, $location_id] = $this->locations($request);
        $tab = in_array($request->input('tab'), ['in_use', 'history', 'setup']) ? $request->input('tab') : 'in_use';
        $business_id = $this->businessId();

        $current = $location_id ? $this->bulk->current($business_id, $location_id) : [];
        $items = BulkItem::where('business_id', $business_id)->with('product.unit', 'variation')->get();
        $filters = [
            'bulk_item_id' => $request->input('bulk_item_id'), 'location_id' => $request->input('h_location_id'),
            'from' => $request->input('from') ?: now()->subDays(30)->format('Y-m-d'), 'to' => $request->input('to') ?: now()->format('Y-m-d'),
            'flag' => $request->input('flag'), 'permitted' => auth()->user()->permitted_locations(),
        ];
        $history = $this->bulk->history($business_id, $filters);
        if ($filters['flag'] === 'low') {
            $history = $history->filter(fn ($c) => $c->item && BulkItems::flag($c, $c->item) === 'low')->values();
        }
        $summary = $this->summary($history);
        $users = [];
        foreach ($items as $it) {
            $users[$it->id] = $this->bulk->users($it);
        }

        return view('bulk_items.index', compact('locations', 'location_id', 'tab', 'current', 'items', 'filters', 'history', 'summary', 'users') + [
            'tu' => $this->tu, 'can_tap' => self::canTap(), 'can_setup' => self::canSetup(),
        ]);
    }

    /** Averages per item and per person who finished the bags, for the history tab. */
    protected function summary($history)
    {
        $items = [];
        $people = [];
        foreach ($history as $c) {
            if (! $c->item) {
                continue;
            }
            $k = $c->bulk_item_id;
            $items[$k] = $items[$k] ?? ['name' => $c->item->name(), 'label' => $c->item->container_label, 'n' => 0, 'containers' => 0, 'yield_sum' => 0, 'lost' => 0, 'low' => 0];
            $items[$k]['n']++;
            $items[$k]['containers'] += $c->containers;
            $items[$k]['yield_sum'] += (float) $c->yield_pct;
            $items[$k]['lost'] += max(0, $c->value);
            $items[$k]['low'] += BulkItems::flag($c, $c->item) === 'low' ? 1 : 0;
            foreach ((array) ($c->breakdown['sellers'] ?? []) as $s) {
                $name = $s['name'] ?: '—';
                $people[$name] = $people[$name] ?? ['name' => $name, 'sold' => 0, 'low_sold' => 0, 'bags' => 0];
                $people[$name]['sold'] += $s['sold'];
                $people[$name]['bags']++;
                if (BulkItems::flag($c, $c->item) === 'low') {
                    $people[$name]['low_sold'] += $s['sold'];
                }
            }
        }
        foreach ($items as &$i) {
            $i['avg_yield'] = $i['n'] ? $i['yield_sum'] / $i['n'] : null;
        }
        usort($people, fn ($a, $b) => $b['sold'] <=> $a['sold']);

        return ['items' => array_values($items), 'people' => $people];
    }

    /** The POS "Bags" window: the cards of the till's location. */
    public function posPanel(Request $request)
    {
        abort_unless(self::canView(), 403, 'Unauthorized action.');
        [$locations, $location_id] = $this->locations($request);
        $current = $location_id ? $this->bulk->current($this->businessId(), $location_id) : [];

        return view('bulk_items.partials.cards', ['current' => $current, 'location_id' => $location_id, 'tu' => $this->tu, 'can_tap' => self::canTap(), 'compact' => $request->input('compact', '1') === '1']);
    }

    public function cycle($id)
    {
        abort_unless(self::canView(), 403, 'Unauthorized action.');
        $c = BulkItemCycle::where('business_id', $this->businessId())->with('item.product.unit', 'item.variation', 'location', 'opener', 'closer')->findOrFail($id);

        return view('bulk_items.partials.cycle', ['c' => $c, 'tu' => $this->tu]);
    }

    // ------------------------------------------------------------------ taps

    public function start(Request $request, $id)
    {
        abort_unless(self::canTap(), 403, 'Unauthorized action.');
        $item = $this->item($id);
        [$locations, $location_id] = $this->locations($request);
        $this->bulk->start($item, $location_id, auth()->id());

        return response()->json(['success' => true, 'msg' => __('bulk.started', ['label' => __('bulk.label_' . $item->container_label), 'name' => $item->name()])]);
    }

    public function finish(Request $request, $id)
    {
        abort_unless(self::canTap(), 403, 'Unauthorized action.');
        $item = $this->item($id);
        [$locations, $location_id] = $this->locations($request);
        try {
            $c = $this->bulk->finish($item, $location_id, auth()->id(), (float) $request->input('containers', 1), ! $request->boolean('none_left'), $request->input('note'));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], 422);
        }
        $flag = BulkItems::flag($c, $item);
        $label = __('bulk.label_' . $item->container_label);
        $msg = __('bulk.finished_' . ($flag ?: 'ok'), [
            'label' => $label, 'pct' => number_format((float) $c->yield_pct, 0),
            'diff' => rtrim(rtrim(number_format(abs($c->actual_usage - $c->expected_usage) / max(0.0001, $item->container_size), 2), '0'), '.'),
            'value' => $this->tu->num_f(abs($c->value), true),
        ]);

        return response()->json(['success' => true, 'flag' => $flag, 'yield' => $c->yield_pct, 'msg' => $msg, 'cycle_id' => $c->id]);
    }

    public function undo($id)
    {
        abort_unless(self::canTap(), 403, 'Unauthorized action.');
        $c = BulkItemCycle::where('business_id', $this->businessId())->findOrFail($id);
        $permitted = auth()->user()->permitted_locations();
        abort_unless($permitted === 'all' || in_array($c->location_id, (array) $permitted), 403, 'Unauthorized action.');
        try {
            $this->bulk->undo($c);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'msg' => __('bulk.undone')]);
    }

    // ------------------------------------------------------------------ set-up

    /** Stock products that can be tracked (search for the set-up). */
    public function products(Request $request)
    {
        abort_unless(self::canSetup(), 403, 'Unauthorized action.');
        $term = trim((string) $request->input('q'));
        $rows = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.business_id', $this->businessId())->whereIn('p.type', ['single', 'variable'])->where('p.enable_stock', 1)->whereNull('v.deleted_at')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('p.name', 'like', '%' . $term . '%')->orWhere('v.sub_sku', 'like', '%' . $term . '%')))
            ->orderBy('p.name')->limit(30)
            ->get(['v.id', 'p.name', 'p.type', 'v.name as vname', 'v.sub_sku', 'u.actual_name as unit']);

        return response()->json(['results' => $rows->map(fn ($r) => [
            'id' => $r->id,
            'text' => $r->name . ($r->type === 'variable' && strtoupper((string) $r->vname) !== 'DUMMY' ? ' - ' . $r->vname : '') . ' (' . $r->sub_sku . ')',
            'unit' => $r->unit,
        ])]);
    }

    protected function validated(Request $request)
    {
        $request->validate([
            'container_label' => 'required|in:' . implode(',', BulkItem::LABELS),
            'container_size' => 'required|numeric|min:0.0001',
            'low_pct' => 'required|integer|min:10|max:100',
            'high_pct' => 'required|integer|min:100|max:300',
        ]);

        return [
            'container_label' => $request->input('container_label'),
            'container_size' => (float) $request->input('container_size'),
            'low_pct' => (int) $request->input('low_pct'),
            'high_pct' => (int) $request->input('high_pct'),
        ];
    }

    public function store(Request $request)
    {
        abort_unless(self::canSetup(), 403, 'Unauthorized action.');
        $business_id = $this->businessId();
        $v = \App\Variation::join('products as p', 'p.id', '=', 'variations.product_id')->where('p.business_id', $business_id)
            ->where('p.enable_stock', 1)->whereIn('p.type', ['single', 'variable'])->select('variations.*')
            ->where('variations.id', (int) $request->input('variation_id'))->firstOrFail();
        if (BulkItem::where('business_id', $business_id)->where('variation_id', $v->id)->exists()) {
            return response()->json(['success' => false, 'msg' => __('bulk.err_exists')], 422);
        }
        $item = BulkItem::create($this->validated($request) + ['business_id' => $business_id, 'product_id' => $v->product_id, 'variation_id' => $v->id, 'is_active' => 1, 'created_by' => auth()->id()]);

        return response()->json(['success' => true, 'msg' => __('bulk.saved'), 'id' => $item->id]);
    }

    public function update(Request $request, $id)
    {
        abort_unless(self::canSetup(), 403, 'Unauthorized action.');
        $item = $this->item($id);
        $item->fill($this->validated($request))->save();

        return response()->json(['success' => true, 'msg' => __('bulk.saved')]);
    }

    public function toggle($id)
    {
        abort_unless(self::canSetup(), 403, 'Unauthorized action.');
        $item = $this->item($id);
        $item->is_active = ! $item->is_active;
        $item->save();

        return response()->json(['success' => true, 'msg' => __('bulk.saved'), 'active' => $item->is_active]);
    }
}
