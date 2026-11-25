<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\GymPlan;
use App\Utils\ClinicWhatsApp;
use App\Utils\Gym;
use App\Utils\Util;
use Illuminate\Http\Request;

/** PosInnova gym set-up: plans and passes, hotel guests, reminders. */
class GymSetupController extends GymBaseController
{
    public function index()
    {
        $this->guard('gym.setup');
        $bid = $this->biz();
        $acc = ClinicWhatsApp::account($bid);

        return view('gym.setup', ['s' => Gym::settings($bid), 'plans' => GymPlan::where('business_id', $bid)->orderBy('sort_order')->orderBy('id')->get(),
            'locations' => BusinessLocation::where('business_id', $bid)->pluck('name', 'id'), 'wa' => $acc, 'waReady' => $acc && $acc->ready(),
            'hotel' => \App\Utils\Hotel::enabled($bid)]);
    }

    public function usualPlans()
    {
        $this->guard('gym.setup');
        $n = Gym::addUsualPlans($this->biz());

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('gym.usual_added', ['n' => $n])]);
    }

    public function addPlan(Request $request)
    {
        $this->guard('gym.setup');
        $name = trim((string) $request->input('name'));
        $kind = in_array($request->input('kind'), GymPlan::KINDS, true) ? $request->input('kind') : null;
        if ($name === '' || ! $kind) {
            return redirect()->back()->with('status', ['success' => 0, 'msg' => __('gym.err_plan_form')]);
        }
        $price = (float) (new Util())->num_uf((string) $request->input('price'));
        GymPlan::create(['business_id' => $this->biz(), 'name' => mb_substr($name, 0, 120), 'kind' => $kind,
            'months' => $kind === 'months' ? max(1, (int) $request->input('months', 1)) : ($kind === 'visits' ? max(1, (int) $request->input('months', 6)) : 0),
            'visits' => $kind === 'visits' ? max(1, (int) $request->input('visits', 10)) : 0,
            'price' => max(0, $price), 'is_active' => $price > 0 ? 1 : 0,
            'sort_order' => (int) GymPlan::where('business_id', $this->biz())->max('sort_order') + 1]);

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('gym.plan_added', ['name' => $name])]);
    }

    public function plans(Request $request)
    {
        $this->guard('gym.setup');
        $u = new Util();
        foreach ((array) $request->input('plans', []) as $id => $row) {
            $p = GymPlan::where('business_id', $this->biz())->find($id);
            if (! $p) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $p->name = mb_substr($name, 0, 120);
            }
            $p->price = max(0, (float) $u->num_uf((string) ($row['price'] ?? 0)));
            if ($p->kind === 'months') {
                $p->months = max(1, (int) ($row['months'] ?? $p->months));
            }
            if ($p->kind === 'visits') {
                $p->visits = max(1, (int) ($row['visits'] ?? $p->visits));
                $p->months = max(1, (int) ($row['months'] ?? $p->months ?: 6));
            }
            $p->is_active = ! empty($row['active']) && $p->price > 0;
            $p->save();
        }

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('gym.plans_saved')]);
    }

    public function settings(Request $request)
    {
        $this->guard('gym.setup');
        $in = $request->only(array_keys(Gym::DEFAULTS));
        foreach (['send_expiring', 'send_expired'] as $f) {
            $in[$f] = $request->boolean($f) ? 1 : 0;
        }
        foreach (['remind_before', 'remind_after'] as $n) {
            if (isset($in[$n])) {
                $in[$n] = max(0, min(60, (int) $in[$n]));
            }
        }
        if (isset($in['guest_rule']) && ! in_array($in['guest_rule'], ['free', 'room', 'same'], true)) {
            unset($in['guest_rule']);
        }
        if (isset($in['member_prefix'])) {
            $in['member_prefix'] = mb_substr(preg_replace('/[^A-Za-z0-9\-\/]/', '', (string) $in['member_prefix']), 0, 8);
        }
        foreach (['tpl_expiring', 'tpl_expired'] as $t) {
            if (isset($in[$t])) {
                $in[$t] = mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string) $in[$t])), 0, 60);
            }
        }
        if (isset($in['location_id']) && ! BusinessLocation::where('business_id', $this->biz())->where('id', $in['location_id'])->exists()) {
            unset($in['location_id']);
        }
        Gym::saveSettings($this->biz(), $in);

        return redirect()->action([self::class, 'index'])->with('status', ['success' => 1, 'msg' => __('gym.settings_saved')]);
    }
}
