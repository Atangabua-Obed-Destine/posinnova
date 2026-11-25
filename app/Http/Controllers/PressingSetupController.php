<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\PressingItem;
use App\PressingPrice;
use App\PressingService;
use App\Utils\ClinicWhatsApp;
use App\Utils\Pressing;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova pressing set-up: (1) the price list, one row per type of item and one column per
 * service, (2) the WhatsApp messages, (3) the rules (express, payment at drop-off, left-over days).
 */
class PressingSetupController extends PressingBaseController
{
    public function index()
    {
        $this->guard('pressing.setup');
        $bid = $this->biz();
        Pressing::ensureServices($bid);
        $acc = ClinicWhatsApp::account($bid);

        return view('pressing.setup', [
            's' => Pressing::settings($bid),
            'services' => Pressing::services($bid, false),
            'items' => PressingItem::where('business_id', $bid)->orderBy('sort_order')->orderBy('name')->get(),
            'prices' => PressingPrice::where('business_id', $bid)->get()->keyBy(fn ($p) => $p->item_id.'|'.$p->service_id),
            'locations' => BusinessLocation::where('business_id', $bid)->pluck('name', 'id'),
            'wa' => $acc, 'waReady' => $acc && $acc->ready(),
        ]);
    }

    /** The price grid (and each item's delay), in one save. */
    public function prices(Request $request)
    {
        $this->guard('pressing.setup');
        $bid = $this->biz();
        $u = new Util();
        $services = PressingService::where('business_id', $bid)->pluck('id')->all();
        DB::transaction(function () use ($request, $bid, $u, $services) {
            foreach ((array) $request->input('items', []) as $item_id => $row) {
                $item = PressingItem::where('business_id', $bid)->find($item_id);
                if (! $item) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                if ($name !== '') {
                    $item->name = mb_substr($name, 0, 120);
                }
                $item->ready_days = max(1, min(60, (int) ($row['ready_days'] ?? $item->ready_days)));
                $item->save();
                foreach ((array) ($row['price'] ?? []) as $svc_id => $value) {
                    if (! in_array((int) $svc_id, $services)) {
                        continue;
                    }
                    $value = trim((string) $value);
                    $guest = trim((string) ($row['guest'][$svc_id] ?? ''));
                    $p = PressingPrice::where('item_id', $item->id)->where('service_id', $svc_id)->first();
                    if ($value === '') {
                        if ($p) {
                            $p->delete(); // not offered any more (past orders keep their own prices)
                        }
                        continue;
                    }
                    $amount = max(0, (float) $u->num_uf($value));
                    $p = $p ?: new PressingPrice(['business_id' => $bid, 'item_id' => $item->id, 'service_id' => (int) $svc_id]);
                    $p->price = $amount;
                    $p->guest_price = $guest === '' ? null : max(0, (float) $u->num_uf($guest));
                    $p->save();
                }
            }
        });

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('pressing.prices_saved')]);
    }

    public function addItem(Request $request)
    {
        $this->guard('pressing.setup');
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return redirect()->back()->with('status', ['success' => 0, 'msg' => __('pressing.err_name')]);
        }
        PressingItem::create(['business_id' => $this->biz(), 'name' => mb_substr($name, 0, 120), 'ready_days' => max(1, (int) $request->input('ready_days', 2)),
            'sort_order' => (int) PressingItem::where('business_id', $this->biz())->max('sort_order') + 1]);

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('pressing.item_added', ['name' => $name])]);
    }

    public function usualItems()
    {
        $this->guard('pressing.setup');
        $n = Pressing::addUsualItems($this->biz());

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('pressing.usual_added', ['n' => $n])]);
    }

    public function toggleItem($id)
    {
        $this->guard('pressing.setup');
        $item = PressingItem::where('business_id', $this->biz())->findOrFail($id);
        $item->is_active = ! $item->is_active;
        $item->save();

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => $item->is_active ? __('pressing.item_on', ['name' => $item->name]) : __('pressing.item_off', ['name' => $item->name])]);
    }

    public function service(Request $request)
    {
        $this->guard('pressing.setup');
        $bid = $this->biz();
        foreach ((array) $request->input('services', []) as $id => $row) {
            $svc = PressingService::where('business_id', $bid)->find($id);
            if (! $svc) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $svc->name = mb_substr($name, 0, 120);
            }
            $svc->is_active = ! empty($row['active']);
            $svc->save();
        }
        $new = trim((string) $request->input('new_service'));
        if ($new !== '') {
            PressingService::create(['business_id' => $bid, 'name' => mb_substr($new, 0, 120), 'sort_order' => (int) PressingService::where('business_id', $bid)->max('sort_order') + 1]);
        }

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('pressing.services_saved')]);
    }

    public function settings(Request $request)
    {
        $this->guard('pressing.setup');
        $in = $request->only(array_keys(Pressing::DEFAULTS));
        unset($in['guest_prices'], $in['charge_to_room']);
        // the hotel options are on the form only when the hotel module is on
        $flags = $request->boolean('hotel_fields') ? ['guest_prices', 'charge_to_room'] : [];
        foreach (array_merge($flags, ['send_received', 'send_ready', 'send_reminder']) as $flag) {
            $in[$flag] = $request->boolean($flag) ? 1 : 0;
        }
        foreach (['express_percent', 'left_over_days', 'reminder_days'] as $n) {
            if (isset($in[$n])) {
                $in[$n] = max(0, (int) $in[$n]);
            }
        }
        foreach (['ready_time', 'express_time'] as $t) {
            if (isset($in[$t]) && ! preg_match('/^\d{1,2}:\d{2}$/', (string) $in[$t])) {
                unset($in[$t]);
            }
        }
        if (isset($in['pay_rule']) && ! in_array($in['pay_rule'], ['any', 'half', 'full'], true)) {
            unset($in['pay_rule']);
        }
        if (isset($in['ticket_prefix'])) {
            $in['ticket_prefix'] = mb_substr(preg_replace('/[^A-Za-z0-9\-\/]/', '', (string) $in['ticket_prefix']), 0, 8);
        }
        foreach (['tpl_received', 'tpl_ready', 'tpl_reminder'] as $t) {
            if (isset($in[$t])) {
                $in[$t] = mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string) $in[$t])), 0, 60);
            }
        }
        if (isset($in['location_id']) && ! BusinessLocation::where('business_id', $this->biz())->where('id', $in['location_id'])->exists()) {
            unset($in['location_id']);
        }
        Pressing::saveSettings($this->biz(), $in);

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('pressing.settings_saved')]);
    }
}
