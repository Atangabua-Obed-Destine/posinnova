<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\ChannelLink;
use App\ChannelProduct;
use App\Utils\ChannelCatalog;
use App\Utils\ChannelClient;
use App\Utils\ChannelException;
use App\Utils\ChannelOrders;
use App\Utils\ChannelSync;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova online channels (E-Ride, contract v1):
 *  - linking: one E-Ride key per selling location, what customers see, the payment to propose;
 *  - products: which products go on E-Ride, own price, unit, low-stock level, blocked categories;
 *  - the POS screen: waiting orders, "Ring up", cancelled orders seen.
 * Switched on per business: Settings › Modules › E-Ride.
 */
class ChannelController extends Controller
{
    private function businessId()
    {
        return (int) request()->session()->get('user.business_id');
    }

    private function moduleOn()
    {
        return in_array('eride', (array) (request()->session()->get('business.enabled_modules') ?: []), true);
    }

    private function canSetUp()
    {
        return $this->moduleOn() && auth()->user()->can('business_settings.access');
    }

    public static function canView()
    {
        return in_array('eride', (array) (session('business.enabled_modules') ?: []), true) && auth()->user()->can('business_settings.access');
    }

    // ---------------------------------------------------------------- linking

    public function index()
    {
        abort_unless($this->canSetUp(), 403);
        $business_id = $this->businessId();
        $locations = BusinessLocation::where('business_id', $business_id)->where('is_active', 1)->orderBy('name')->get();
        $links = ChannelLink::where('business_id', $business_id)->shop()->get()->keyBy('location_id');
        $util = new Util();
        $pay = [];
        foreach ($locations as $loc) {
            $pay[$loc->id] = $util->payment_types($loc);
        }
        $published = ChannelProduct::where('business_id', $business_id)->where('publish', 1)->where('never', 0)->count();

        return view('channel.index', compact('locations', 'links', 'pay', 'published'));
    }

    /** The default "Mobile Money" method of a location: a payment label that reads like one. */
    public static function guessMobileMoney(array $types)
    {
        foreach ($types as $k => $label) {
            if (preg_match('/mobile|momo|orange|mtn|fapshi/i', (string) $label)) {
                return $k;
            }
        }

        return null;
    }

    public function save(Request $request, $location_id)
    {
        abort_unless($this->canSetUp(), 403);
        $business_id = $this->businessId();
        $loc = BusinessLocation::where('business_id', $business_id)->findOrFail($location_id);
        $link = ChannelLink::where('business_id', $business_id)->where('location_id', $loc->id)->shop()->first();

        $url = ChannelClient::cleanBaseUrl($request->input('base_url'));
        if (! $url) {
            return back()->withInput()->with('channel_error_'.$loc->id, __('channel.err_url'));
        }
        $key = trim((string) $request->input('api_key', ''));
        if ($key === '' && ! $link) {
            return back()->withInput()->with('channel_error_'.$loc->id, __('channel.err_key'));
        }
        if ($key !== '' && ! preg_match('/^erk_[A-Za-z0-9]{20,80}$/', $key)) {
            return back()->withInput()->with('channel_error_'.$loc->id, __('channel.err_key_shape'));
        }
        $types = (new Util())->payment_types($loc);
        $pay = $request->input('pay_method');
        if (! array_key_exists((string) $pay, $types)) {
            $pay = self::guessMobileMoney($types);
        }

        $f = [
            'base_url' => $url,
            'qty_display' => $request->input('qty_display') === 'exact' ? 'exact' : 'available',
            'pay_method' => $pay,
            'is_active' => true,
        ];
        $resend = false;
        if (! $link) {
            $link = new ChannelLink(['business_id' => $business_id, 'location_id' => $loc->id, 'channel' => 'eride']);
            $resend = true;
        } elseif ($link->qty_display !== $f['qty_display'] || $link->base_url !== $f['base_url'] || $key !== '') {
            $resend = true;
        }
        if ($key !== '') {
            $f['api_key'] = $key;
            $f['key_hint'] = substr($key, -4);
            $f['revoked_at'] = null;
        }
        $link->forceFill($f);
        if ($resend) {
            $link->full_requested = true;
        }
        $link->save();

        // Check it at once; the catalogue goes with the next minute of the scheduler (or now, below).
        try {
            ChannelSync::hello($link);
            if (! $link->linked_at) {
                $link->forceFill(['linked_at' => now()])->save();
            }
            $msg = __('channel.saved_ok', ['branch' => $link->branch_label ?: $loc->name]);
            if ($resend) {
                $done = ChannelSync::run($link->fresh(), ['full' => true]);
                if (! empty($done['error'])) {
                    return back()->with('channel_error_'.$loc->id, __('channel.err_send', ['error' => $done['error']]));
                }
            }

            return back()->with('channel_ok_'.$loc->id, $msg);
        } catch (ChannelException $e) {
            ChannelSync::fail($link, $e);

            return back()->with('channel_error_'.$loc->id, $this->explain($e));
        }
    }

    private function explain(ChannelException $e)
    {
        if ($e->isRevoked()) {
            return __('channel.err_revoked');
        }
        if ($e->status === 0) {
            return __('channel.err_network', ['error' => $e->getMessage()]);
        }

        return __('channel.err_answer', ['error' => $e->errorCode.($e->getMessage() !== $e->errorCode ? ' — '.$e->getMessage() : '')]);
    }

    public function test($location_id)
    {
        abort_unless($this->canSetUp(), 403);
        $link = ChannelLink::where('business_id', $this->businessId())->where('location_id', $location_id)->shop()->firstOrFail();
        try {
            ChannelSync::hello($link);

            return back()->with('channel_ok_'.$location_id, __('channel.test_ok', ['branch' => $link->branch_label ?: '—']));
        } catch (ChannelException $e) {
            ChannelSync::fail($link, $e);

            return back()->with('channel_error_'.$location_id, $this->explain($e));
        }
    }

    public function resend($location_id)
    {
        abort_unless($this->canSetUp(), 403);
        $link = ChannelLink::where('business_id', $this->businessId())->where('location_id', $location_id)->shop()->firstOrFail();
        $link->forceFill(['full_requested' => true])->save();
        $done = ChannelSync::run($link->fresh(), ['full' => true]);
        if (! empty($done['error'])) {
            return back()->with('channel_error_'.$location_id, __('channel.err_send', ['error' => $done['error']]));
        }

        return back()->with('channel_ok_'.$location_id, __('channel.resent_ok', ['n' => $link->fresh()->published]));
    }

    public function toggle($location_id)
    {
        abort_unless($this->canSetUp(), 403);
        $link = ChannelLink::where('business_id', $this->businessId())->where('location_id', $location_id)->shop()->firstOrFail();
        $link->forceFill(['is_active' => ! $link->is_active, 'full_requested' => true])->save();

        return back()->with('channel_ok_'.$location_id, $link->is_active ? __('channel.on_ok') : __('channel.off_ok'));
    }

    public function remove($location_id)
    {
        abort_unless($this->canSetUp(), 403);
        $link = ChannelLink::where('business_id', $this->businessId())->where('location_id', $location_id)->shop()->firstOrFail();
        DB::table('channel_sent')->where('link_id', $link->id)->delete();
        DB::table('channel_outbox')->where('link_id', $link->id)->whereNull('done_at')->update(['done_at' => now(), 'last_error' => 'link removed in POS']);
        $link->delete();

        return back()->with('channel_ok_'.$location_id, __('channel.removed_ok'));
    }

    // ---------------------------------------------------------------- products

    public function products(Request $request)
    {
        abort_unless($this->canSetUp(), 403);
        $business_id = $this->businessId();
        $show = in_array($request->input('show'), ['on', 'off', 'never'], true) ? $request->input('show') : 'all';
        $category = (int) $request->input('category');
        $search = trim((string) $request->input('q', ''));

        $links = ChannelLink::where('business_id', $business_id)->shop()->with('location')->get();
        $loc_id = (int) $request->input('location') ?: optional($links->first())->location_id;

        $q = $this->productQuery($business_id, $show, $category, $search);
        $products = $q->select([
            'p.id', 'p.name', 'p.sku', 'p.type', 'p.unit_id', 'p.sub_unit_ids', 'p.enable_stock', 'p.alert_quantity', 'p.category_id',
            'cp.publish', 'cp.never', 'cp.price as own_price', 'cp.unit_id as pub_unit_id', 'cp.low_stock',
        ])->orderBy('p.name')->paginate(50)->withQueryString();

        $ids = $products->pluck('id')->all();
        $prices = DB::table('variations')->whereIn('product_id', $ids ?: [0])->whereNull('deleted_at')
            ->groupBy('product_id')->select('product_id', DB::raw('MIN(sell_price_inc_tax) as lo'), DB::raw('MAX(sell_price_inc_tax) as hi'))->get()->keyBy('product_id');
        $stock = $loc_id ? DB::table('variation_location_details')->whereIn('product_id', $ids ?: [0])->where('location_id', $loc_id)
            ->groupBy('product_id')->select('product_id', DB::raw('SUM(qty_available) as q'))->pluck('q', 'product_id') : collect();
        $units = DB::table('units')->where('business_id', $business_id)->whereNull('deleted_at')->get()->keyBy('id');
        $cats = DB::table('categories')->where('business_id', $business_id)->whereNull('deleted_at')->where('category_type', 'product')
            ->orderBy('name')->get(['id', 'name', 'parent_id']);
        $blocked = ChannelCatalog::blockedCategories($business_id);
        $counts = [
            'on' => ChannelProduct::where('business_id', $business_id)->where('publish', 1)->where('never', 0)->count(),
            'never' => ChannelProduct::where('business_id', $business_id)->where('never', 1)->count(),
        ];
        $matching = $products->total();

        return view('channel.products', compact('products', 'prices', 'stock', 'units', 'cats', 'blocked', 'show', 'category', 'search', 'links', 'loc_id', 'counts', 'matching'));
    }

    private function productQuery($business_id, $show, $category, $search)
    {
        $q = DB::table('products as p')
            ->leftJoin('channel_products as cp', function ($j) {
                $j->on('cp.product_id', '=', 'p.id')->on('cp.business_id', '=', 'p.business_id');
            })
            ->where('p.business_id', $business_id)
            ->where('p.is_inactive', 0)->where('p.not_for_selling', 0)
            ->whereIn('p.type', ['single', 'variable']);
        \App\Product::hideSystemServices($q, 'p');
        if ($show === 'on') {
            $q->where('cp.publish', 1)->where('cp.never', 0);
        } elseif ($show === 'off') {
            $q->where(function ($w) {
                $w->whereNull('cp.id')->orWhere('cp.publish', 0);
            })->where(function ($w) {
                $w->whereNull('cp.never')->orWhere('cp.never', 0);
            });
        } elseif ($show === 'never') {
            $q->where('cp.never', 1);
        }
        if ($category) {
            $q->where(function ($w) use ($category) {
                $w->where('p.category_id', $category)->orWhere('p.sub_category_id', $category);
            });
        }
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('p.name', 'like', '%'.$search.'%')->orWhere('p.sku', 'like', '%'.$search.'%')
                    ->orWhereExists(function ($e) use ($search) {
                        $e->select(DB::raw(1))->from('variations as v')->whereColumn('v.product_id', 'p.id')->where('v.sub_sku', 'like', '%'.$search.'%');
                    });
            });
        }

        return $q;
    }

    /** One product: the switches and the figures. JSON for the inline table. */
    public function saveProduct(Request $request, $product_id)
    {
        abort_unless($this->canSetUp(), 403);
        $business_id = $this->businessId();
        $p = DB::table('products')->where('business_id', $business_id)->where('id', $product_id)->first(['id', 'type', 'unit_id', 'sub_unit_ids']);
        abort_unless($p, 404);
        $cp = ChannelProduct::firstOrNew(['business_id' => $business_id, 'product_id' => $p->id]);

        if ($request->has('publish')) {
            $cp->publish = (bool) $request->input('publish');
        }
        if ($request->has('never')) {
            $cp->never = (bool) $request->input('never');
            if ($cp->never) {
                $cp->publish = false;
            }
        }
        if ($cp->publish && $cp->never) {
            return response()->json(['ok' => false, 'msg' => __('channel.err_never')], 422);
        }
        if ($request->has('price')) {
            $v = trim((string) $request->input('price'));
            if ($v === '') {
                $cp->price = null;
            } else {
                $n = (new Util())->num_uf($v);
                if ($p->type !== 'single' || $n < 0) {
                    return response()->json(['ok' => false, 'msg' => __('channel.err_price')], 422);
                }
                $cp->price = $n > 0 ? $n : null;
            }
        }
        if ($request->has('low_stock')) {
            $v = trim((string) $request->input('low_stock'));
            $n = $v === '' ? null : (new Util())->num_uf($v);
            if ($n !== null && $n < 0) {
                return response()->json(['ok' => false, 'msg' => __('channel.err_low')], 422);
            }
            $cp->low_stock = $n;
        }
        if ($request->has('unit_id')) {
            $u = (int) $request->input('unit_id');
            $allowed = array_keys((new Util())->getSubUnits($business_id, $p->unit_id, true, $p->id));
            if ($u && ! in_array($u, array_map('intval', $allowed), true) && $u !== (int) $p->unit_id) {
                return response()->json(['ok' => false, 'msg' => __('channel.err_unit')], 422);
            }
            $cp->unit_id = ($u && $u !== (int) $p->unit_id) ? $u : null;
        }
        $cp->save();

        return response()->json(['ok' => true, 'publish' => $cp->publish, 'never' => $cp->never]);
    }

    /** Several products at once: on / off / never, for the ticked rows or for every matching product. */
    public function bulk(Request $request)
    {
        abort_unless($this->canSetUp(), 403);
        $business_id = $this->businessId();
        $action = $request->input('action');
        abort_unless(in_array($action, ['on', 'off', 'never', 'allow'], true), 422);

        if ($request->input('scope') === 'matching') {
            $ids = $this->productQuery($business_id, $request->input('show', 'all'), (int) $request->input('category'), trim((string) $request->input('q', '')))
                ->pluck('p.id')->all();
        } else {
            $ids = DB::table('products')->where('business_id', $business_id)->whereIn('id', array_map('intval', (array) $request->input('ids', [])))->pluck('id')->all();
        }
        $n = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($chunk as $id) {
                $cp = ChannelProduct::firstOrNew(['business_id' => $business_id, 'product_id' => $id]);
                if ($action === 'on' && $cp->never) {
                    continue; // "Never on E-Ride" wins: it has to be lifted one by one, on purpose
                }
                $cp->publish = $action === 'on';
                if ($action === 'never') {
                    $cp->never = true;
                }
                if ($action === 'allow') {
                    $cp->never = false;
                    $cp->publish = false;
                }
                $cp->save();
                $n++;
            }
        }

        return back()->with('status', ['success' => 1, 'msg' => __('channel.bulk_ok', ['n' => $n])]);
    }

    public function categories(Request $request)
    {
        abort_unless($this->canSetUp(), 403);
        $business_id = $this->businessId();
        $ids = DB::table('categories')->where('business_id', $business_id)->whereIn('id', array_map('intval', (array) $request->input('blocked', [])))->pluck('id')->all();
        DB::transaction(function () use ($business_id, $ids) {
            DB::table('channel_category_blocks')->where('business_id', $business_id)->whereNotIn('category_id', $ids ?: [0])->delete();
            foreach ($ids as $id) {
                DB::table('channel_category_blocks')->updateOrInsert(['business_id' => $business_id, 'category_id' => $id], ['updated_at' => now(), 'created_at' => now()]);
            }
        });
        // A blocked category is a change without a timestamp on the products: compare everything at the next minute.
        ChannelLink::where('business_id', $business_id)->shop()->update(['last_hourly_at' => null]);

        return back()->with('status', ['success' => 1, 'msg' => __('channel.cats_ok', ['n' => count($ids)])]);
    }

    // ---------------------------------------------------------------- the POS screen

    private function posLink(Request $request)
    {
        if (! $this->moduleOn() || ! auth()->user()->can('sell.create')) {
            abort(403);
        }
        $business_id = $this->businessId();
        $loc = (int) $request->input('location_id');
        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all' && ! in_array($loc, (array) $permitted)) {
            abort(403);
        }

        return ChannelOrders::linkFor($business_id, $loc);
    }

    public function posPending(Request $request)
    {
        $link = $this->posLink($request);
        if (! $link) {
            return response()->json(['linked' => false]);
        }

        return response()->json(['linked' => true] + ChannelOrders::pending($link));
    }

    public function posOrder(Request $request, $order_id)
    {
        $link = $this->posLink($request);
        abort_unless($link, 404);
        try {
            return response()->json(ChannelOrders::forRingUp($link, $order_id, auth()->id()));
        } catch (ChannelException $e) {
            ChannelSync::fail($link, $e);
            $msg = $e->status === 404 ? __('channel.pos_order_gone') : ($e->status === 0 ? __('channel.pos_offline') : $this->explain($e));

            return response()->json(['status' => 'error', 'message' => $msg], 200);
        }
    }

    public function posCancelSeen(Request $request)
    {
        $link = $this->posLink($request);
        abort_unless($link, 404);
        ChannelOrders::cancelSeen($link, (string) $request->input('order_id'), $request->input('number'));
        Cache::forget('channel_pending_'.$link->id);

        return response()->json(['ok' => true]);
    }
}
