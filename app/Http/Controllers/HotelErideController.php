<?php

namespace App\Http\Controllers;

use App\ChannelLink;
use App\Utils\ChannelClient;
use App\Utils\ChannelException;
use App\Utils\ChannelHotel;
use App\Utils\ChannelSync;
use App\Utils\Hotel;
use Illuminate\Http\Request;

/**
 * PosInnova hotel <-> E-Ride Hotels: the link (Hotel setup › How the hotel works) and the E-Ride
 * requests on the hotel home (keep a room, decline, a short reply). POS only calls out.
 */
class HotelErideController extends HotelBaseController
{
    private function link()
    {
        return ChannelLink::where('business_id', $this->biz())->where('channel', ChannelHotel::CHANNEL)->first();
    }

    private function back($msg, $ok = true)
    {
        return redirect()->action([HotelSetupController::class, 'index'], ['tab' => 'settings'])
            ->with($ok ? 'eride_ok' : 'eride_error', $msg)->withFragment('ht_eride');
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

    public function save(Request $request)
    {
        $this->guard('hotel.setup');
        $biz = $this->biz();
        $link = $this->link();
        $url = ChannelClient::cleanBaseUrl($request->input('base_url'));
        if (! $url) {
            return $this->back(__('channel.err_url'), false);
        }
        $key = trim((string) $request->input('api_key', ''));
        if ($key === '' && ! $link) {
            return $this->back(__('channel.err_key'), false);
        }
        if ($key !== '' && ! preg_match('/^erh_[A-Za-z0-9]{20,80}$/', $key)) {
            return $this->back(__('hotel.eride_err_key_shape'), false);
        }
        $time = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $request->input('eride_hold_time')) ? $request->input('eride_hold_time') : '18:00';
        Hotel::saveSettings($biz, ['eride_hold_time' => $time]);

        $loc = Hotel::settings($biz)['location_id'];
        if (! $link) {
            $link = new ChannelLink(['business_id' => $biz, 'location_id' => $loc, 'channel' => ChannelHotel::CHANNEL]);
        }
        $f = ['base_url' => $url, 'is_active' => true, 'location_id' => $loc, 'full_requested' => true];
        if ($key !== '') {
            $f += ['api_key' => $key, 'key_hint' => substr($key, -4), 'revoked_at' => null];
        }
        $link->forceFill($f)->save();

        try {
            ChannelSync::hello($link);
            if (! $link->linked_at) {
                $link->forceFill(['linked_at' => now()])->save();
            }
            $done = ChannelHotel::run($link->fresh(), ['full' => true]);
            if (! empty($done['error'])) {
                return $this->back(__('channel.err_send', ['error' => $done['error']]), false);
            }

            return $this->back(__('hotel.eride_linked', ['hotel' => $link->fresh()->branch_label ?: '—']));
        } catch (ChannelException $e) {
            ChannelSync::fail($link, $e);

            return $this->back($this->explain($e), false);
        }
    }

    public function test()
    {
        $this->guard('hotel.setup');
        $link = $this->link();
        abort_unless($link, 404);
        try {
            ChannelSync::hello($link);

            return $this->back(__('hotel.eride_test_ok', ['hotel' => $link->branch_label ?: '—']));
        } catch (ChannelException $e) {
            ChannelSync::fail($link, $e);

            return $this->back($this->explain($e), false);
        }
    }

    public function resend()
    {
        $this->guard('hotel.setup');
        $link = $this->link();
        abort_unless($link, 404);
        $done = ChannelHotel::run($link, ['full' => true]);

        return ! empty($done['error']) ? $this->back(__('channel.err_send', ['error' => $done['error']]), false) : $this->back(__('hotel.eride_resent'));
    }

    public function toggle()
    {
        $this->guard('hotel.setup');
        $link = $this->link();
        abort_unless($link, 404);
        $link->forceFill(['is_active' => ! $link->is_active, 'full_requested' => true])->save();

        return $this->back($link->is_active ? __('channel.on_ok') : __('hotel.eride_off_ok'));
    }

    public function remove()
    {
        $this->guard('hotel.setup');
        $link = $this->link();
        abort_unless($link, 404);
        \DB::table('channel_hotel_sent')->where('link_id', $link->id)->delete();
        \DB::table('channel_outbox')->where('link_id', $link->id)->whereNull('done_at')->update(['done_at' => now(), 'last_error' => 'link removed in POS']);
        $link->delete();

        return $this->back(__('channel.removed_ok'));
    }

    // ---------------------------------------------------------------- the hotel home

    private function live()
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $link = ChannelHotel::link($this->biz());
        abort_unless($link, 404);

        return $link;
    }

    public function inbox()
    {
        $this->guard('hotel.reservations', 'hotel.front_desk', 'hotel.view');
        $link = ChannelHotel::link($this->biz());
        if (! $link) {
            return response()->json(['linked' => false]);
        }

        return response()->json(['linked' => true, 'hold_time' => Hotel::settings($this->biz())['eride_hold_time']] + ChannelHotel::pending($link));
    }

    public function keep(Request $request, $inquiry_id)
    {
        $link = $this->live();
        try {
            $r = ChannelHotel::keep($link, $inquiry_id, $request->only(['room_type_id', 'rooms', 'status', 'until', 'arrival', 'departure']), auth()->id());
        } catch (ChannelException $e) {
            ChannelSync::fail($link, $e);

            return response()->json(['ok' => false, 'message' => $e->status === 404 ? __('hotel.eride_closed') : ($e->status === 0 ? __('channel.pos_offline') : $this->explain($e))]);
        }
        if ($r['ok']) {
            $r['message'] = __('hotel.eride_kept_ok', ['refs' => implode(', ', $r['refs'])]).($r['delivered'] ? '' : ' '.__('channel.pos_queued'));
        }

        return response()->json($r);
    }

    public function decline(Request $request, $inquiry_id)
    {
        $link = $this->live();
        $r = ChannelHotel::decline($link, $inquiry_id, $request->input('reason'), $request->input('text'), auth()->id());

        return response()->json($r + ['message' => $r['error'] ? $r['error'] : __('hotel.eride_declined_ok').($r['delivered'] ? '' : ' '.__('channel.pos_queued'))]);
    }

    public function reply(Request $request, $inquiry_id)
    {
        $link = $this->live();
        $text = trim((string) $request->input('text'));
        if ($text === '') {
            return response()->json(['ok' => false, 'message' => __('hotel.eride_reply_empty')], 422);
        }
        $r = ChannelHotel::reply($link, $inquiry_id, $text, trim((string) auth()->user()->first_name));

        return response()->json($r + ['message' => $r['error'] ? $r['error'] : __('hotel.eride_reply_ok').($r['delivered'] ? '' : ' '.__('channel.pos_queued'))]);
    }
}
