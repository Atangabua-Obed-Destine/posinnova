<?php

namespace App\Http\Controllers;

use App\HotelReservation;
use App\PressingOrder;
use App\Utils\Hotel;
use App\Utils\HotelRoomCharge;
use App\Utils\Pressing;
use App\Utils\PressingException;
use App\Utils\PressingWhatsApp;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * PosInnova pressing: the counter board, taking an order, its page (progress, ready, money,
 * messages, problems), the printed ticket, collection and the reports. The module is switched on
 * per business (Settings › Modules › Pressing); each action names its permission (Admins have all).
 */
class PressingController extends PressingBaseController
{
    // ---------------------------------------------------------------- the board

    public function index(Request $request)
    {
        $this->guard('pressing.view', 'pressing.counter', 'pressing.workshop');
        $bid = $this->biz();
        $found = $request->filled('q') ? Pressing::search($bid, $request->input('q')) : null;
        if ($found && $found->count() === 1) {
            return redirect()->action([self::class, 'show'], [$found->first()->id]);
        }
        $filter = in_array($request->input('f'), ['express', 'guests', 'outside'], true) ? $request->input('f') : 'all';
        $board = Pressing::board($bid, $filter);
        $ids = collect($board['columns'])->flatten()->pluck('id')->all();

        return view('pressing.index', [
            'board' => $board, 'filter' => $filter, 'found' => $found, 'q' => $request->input('q'),
            'messages' => Pressing::lastMessages($ids), 'hotel' => Hotel::enabled($bid),
            'needsSetup' => ! \App\PressingPrice::where('business_id', $bid)->exists(),
        ]);
    }

    // ---------------------------------------------------------------- a new order

    public function create()
    {
        $this->guard('pressing.counter');
        $bid = $this->biz();
        Pressing::ensureServices($bid);
        $s = Pressing::settings($bid);
        $list = Pressing::priceList($bid)->filter(fn ($r) => $r['prices']->isNotEmpty())->values();
        $guests = Hotel::enabled($bid) ? HotelRoomCharge::inHouse($bid) : [];

        return view('pressing.create', [
            'list' => $list, 'services' => Pressing::services($bid), 's' => $s, 'guests' => $guests,
            'roomBill' => ! empty($s['charge_to_room']) && HotelRoomCharge::enabled($bid),
            'methods' => (new Util())->payment_types(null, false, $bid),
            'readyDays' => $list->mapWithKeys(fn ($r) => [$r['item']->id => (int) $r['item']->ready_days])->all(),
            'guestPrices' => ! empty($s['guest_prices']),
        ]);
    }

    public function store(Request $request)
    {
        $this->guard('pressing.counter');
        $pieces = json_decode((string) $request->input('pieces_json'), true);
        $data = $request->only(['for', 'name', 'mobile', 'reservation_id', 'express', 'ready_at', 'pay', 'amount', 'method', 'whatsapp', 'lang', 'note']);
        $data['pieces'] = is_array($pieces) ? $pieces : [];
        if (! empty($data['amount'])) {
            $data['amount'] = (new Util())->num_uf($data['amount']);
        }
        if (! empty($data['ready_at'])) {
            try {
                $data['ready_at'] = Carbon::parse(str_replace('T', ' ', $data['ready_at']))->format('Y-m-d H:i');
            } catch (\Throwable $e) {
                unset($data['ready_at']);
            }
        }

        return $this->attempt(
            fn () => Pressing::createOrder($this->biz(), $data, auth()->id()),
            fn ($o) => __('pressing.saved', ['ticket' => $o->ticket_no]),
            fn ($o) => redirect()->action([self::class, 'show'], [$o->id, 'print' => 1])
        );
    }

    // ---------------------------------------------------------------- one order

    public function show(Request $request, $id)
    {
        $this->guard('pressing.view', 'pressing.counter', 'pressing.workshop');
        $o = $this->order($id);
        $msgs = \Illuminate\Support\Facades\Schema::hasColumn('clinic_whatsapp_messages', 'pressing_order_id')
            ? \App\ClinicWhatsappMessage::where('pressing_order_id', $o->id)->orderBy('id')->get() : collect();

        return view('pressing.show', [
            'o' => $o, 'm' => Pressing::money($o), 'messages' => $msgs, 'print' => $request->boolean('print'),
            'links' => collect(PressingWhatsApp::KINDS)->mapWithKeys(fn ($k) => [$k => PressingWhatsApp::link($o, $k)])->all(),
            'auto' => collect(PressingWhatsApp::KINDS)->mapWithKeys(fn ($k) => [$k => PressingWhatsApp::canSend($o->business_id, $k, $o->lang)])->all(),
            'methods' => (new Util())->payment_types(null, false, $this->biz()),
        ]);
    }

    public function stage(Request $request, $id)
    {
        $this->guard('pressing.workshop', 'pressing.counter');
        $o = $this->order($id);
        $status = (string) $request->input('status');

        return $this->attempt(fn () => Pressing::move($o, $status, auth()->id()), __('pressing.moved', ['ticket' => $o->ticket_no, 'stage' => __('pressing.st_'.$status)]));
    }

    public function ready($id)
    {
        $this->guard('pressing.workshop', 'pressing.counter');
        $o = $this->order($id);

        return $this->attempt(fn () => Pressing::markReady($o, auth()->id()), function ($msg) use ($o) {
            return $msg && $msg->status !== 'failed' ? __('pressing.ready_sent', ['ticket' => $o->ticket_no]) : __('pressing.ready_link', ['ticket' => $o->ticket_no]);
        });
    }

    public function pay(Request $request, $id)
    {
        $this->guard('pressing.counter');
        $o = $this->order($id);
        $amount = (new Util())->num_uf((string) $request->input('amount'));

        return $this->attempt(fn () => Pressing::recordPayment($o, $amount, $request->input('method', 'cash'), auth()->id()),
            __('pressing.paid', ['amount' => Pressing::format($o->business_id, $amount)]));
    }

    public function notify(Request $request, $id)
    {
        $this->guard('pressing.counter', 'pressing.workshop');
        $o = $this->order($id);
        $kind = in_array($request->input('kind'), PressingWhatsApp::KINDS, true) ? $request->input('kind') : 'ready';

        return $this->attempt(function () use ($o, $kind) {
            $msg = PressingWhatsApp::notify($o, $kind, auth()->id());
            if (! $msg) {
                throw new PressingException(__('pressing.wa_not_sent'));
            }
            if ($msg->status === 'failed') {
                throw new PressingException(__('pressing.wa_send_failed', ['why' => $msg->error_message]));
            }

            return $msg;
        }, __('pressing.wa_sent'));
    }

    public function cancel(Request $request, $id)
    {
        $this->guard('pressing.counter');
        $o = $this->order($id);

        return $this->attempt(fn () => Pressing::cancel($o, $request->input('reason'), auth()->id()), __('pressing.cancelled', ['ticket' => $o->ticket_no]));
    }

    public function problem(Request $request, $id)
    {
        $this->guard('pressing.counter');
        $o = $this->order($id);
        $data = $request->only(['kind', 'piece_id', 'description', 'outcome', 'amount', 'method']);
        if (! empty($data['amount'])) {
            $data['amount'] = (new Util())->num_uf($data['amount']);
        }

        return $this->attempt(fn () => Pressing::reportProblem($o, $data, auth()->id()), __('pressing.problem_saved'));
    }

    public function ticket($id)
    {
        $this->guard('pressing.view', 'pressing.counter', 'pressing.workshop');
        $o = $this->order($id);

        return view('pressing.ticket', ['o' => $o, 'm' => Pressing::money($o), 'business' => \App\Business::find($this->biz()),
            'location' => \App\BusinessLocation::find($o->location_id)]);
    }

    // ---------------------------------------------------------------- collection

    public function collectFind(Request $request)
    {
        $this->guard('pressing.counter');
        $found = $request->filled('q') ? Pressing::search($this->biz(), $request->input('q')) : null;
        if ($found && $found->count() === 1) {
            return redirect()->action([self::class, 'collectForm'], [$found->first()->id]);
        }

        return view('pressing.collect_find', ['found' => $found, 'q' => $request->input('q'),
            'ready' => PressingOrder::where('business_id', $this->biz())->where('status', 'ready')->with('contact:id,name,mobile')->withCount('pieces')->orderBy('ready_marked_at')->limit(60)->get()]);
    }

    public function collectForm($id)
    {
        $this->guard('pressing.counter');
        $o = $this->order($id);

        return view('pressing.collect', ['o' => $o, 'm' => Pressing::money($o), 'methods' => (new Util())->payment_types(null, false, $this->biz())]);
    }

    public function collect(Request $request, $id)
    {
        $this->guard('pressing.counter');
        $o = $this->order($id);
        $amount = (new Util())->num_uf((string) $request->input('amount', 0));

        return $this->attempt(
            fn () => Pressing::collect($o, (array) $request->input('handed', []), $amount, $request->input('method', 'cash'), $request->input('collected_by'), auth()->id()),
            __('pressing.collected', ['ticket' => $o->ticket_no]),
            fn ($r) => redirect()->action([self::class, 'show'], [$o->id])
        );
    }

    // ---------------------------------------------------------------- reports

    public function reports(Request $request)
    {
        $this->guard('pressing.reports');
        $from = $request->input('from') ? Carbon::parse($request->input('from')) : now()->startOfMonth();
        $to = $request->input('to') ? Carbon::parse($request->input('to')) : now()->endOfMonth();

        return view('pressing.reports', ['r' => Pressing::report($this->biz(), $from, $to), 'from' => $from, 'to' => $to]);
    }
}
