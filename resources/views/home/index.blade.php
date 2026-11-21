@extends('layouts.app')
@section('title', __('home.home'))

@section('content')
@php
    // PosInnova: the I-NNOVA home of a shop / pharmacy (the hospital home is home/hospital.blade.php).
    // The figures keep the class names public/js/home.js fills (total_sell, net, ...).
    $ih_tc = session('business.theme_color') ?: 'primary';
    $ih_user = auth()->user();
    $ih_hour = (int) now()->format('G');
    $ih_greet = $ih_hour < 12 ? 'home.ih_good_morning' : ($ih_hour < 18 ? 'home.ih_good_afternoon' : 'home.ih_good_evening');
    $ih_name = trim(preg_replace('/\s+/', ' ', ($ih_user->surname ?? '') . ' ' . $ih_user->first_name));
    $ih_mods = (array) (session('business.enabled_modules') ?: []);
    $ih_data = $ih_user->can('dashboard.data');
    $ih_admin = $ih_data && ! empty($is_admin);
    $ih_paths = [
        'cart' => '<path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 17h-11v-14h-2"/><path d="M6 5l14 1l-1 7h-13"/>',
        'pos' => '<path d="M3 5a1 1 0 0 1 1 -1h16a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1v-10z"/><path d="M7 20h10"/><path d="M9 16v4"/><path d="M15 16v4"/>',
        'invoice' => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 7l1 0"/><path d="M9 13l6 0"/><path d="M13 17l2 0"/>',
        'cash' => '<path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2"/><path d="M14.8 8a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1"/><path d="M12 6v10"/>',
        'return' => '<path d="M21 7l-18 0"/><path d="M18 10l3 -3l-3 -3"/><path d="M6 20l-3 -3l3 -3"/><path d="M3 17l18 0"/>',
        'download' => '<path d="M12 3v12"/><path d="M16 11l-4 4l-4 -4"/><path d="M3 12a9 9 0 0 0 18 0"/>',
        'alert' => '<path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/>',
        'refund' => '<path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2"/><path d="M15 14v-2a2 2 0 0 0 -2 -2h-4l2 -2m0 4l-2 -2"/>',
        'wallet' => '<path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/><path d="M20 12v4h-4a2 2 0 0 1 0 -4h4"/>',
        'package' => '<path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5"/><path d="M12 12l8 -4.5"/><path d="M12 12l0 9"/><path d="M12 12l-8 -4.5"/>',
        'truck' => '<path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M20 18v3"/><path d="M16 16v5"/><path d="M12 13v8"/><path d="M8 16v5"/><path d="M3 11c6 0 5 -5 9 -5s4 3 7 3"/>',
        'clock' => '<path d="M20.986 12.502a9 9 0 1 0 -5.973 7.98"/><path d="M12 7v5l3 3"/><path d="M19 16v3"/><path d="M19 22v.01"/>',
        'users' => '<path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"/><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0 -3 -3.85"/>',
        'calendar' => '<path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z"/><path d="M16 3v4"/><path d="M8 3v4"/><path d="M4 11h16"/>',
        'down' => '<path d="M6 9l6 6l6 -6"/>',
        'hospital' => '<path d="M3 21l18 0"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16"/><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4"/><path d="M10 9l4 0"/><path d="M12 7l0 4"/>',
        'store' => '<path d="M3 21l18 0"/><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4"/><path d="M5 21l0 -10.15"/><path d="M19 21l0 -10.15"/><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4"/>',
    ];
    $ih_icon = function ($k) use ($ih_paths) {
        return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $ih_paths[$k] . '</svg>';
    };
    // Quick actions: only what this person may do.
    $ih_actions = [];
    // (POS is not here: the header has it on every screen.)
    if ($ih_user->can('direct_sell.access') && in_array('add_sale', $ih_mods)) {
        $ih_actions[] = [action([\App\Http\Controllers\SellController::class, 'create']), __('sale.add_sale'), 'invoice', true];
    }
    if ($ih_user->can('purchase.create') && in_array('purchases', $ih_mods)) {
        $ih_actions[] = [action([\App\Http\Controllers\PurchaseController::class, 'create']), __('purchase.add_purchase'), 'truck', false];
    }
    if ($ih_user->can('product.create')) {
        $ih_actions[] = [action([\App\Http\Controllers\ProductController::class, 'create']), __('product.add_product'), 'package', false];
    }
    if ($ih_user->can('expense.add') && in_array('expenses', $ih_mods)) {
        $ih_actions[] = [action([\App\Http\Controllers\ExpenseController::class, 'create']), __('expense.add_expense'), 'wallet', false];
    }
    if ($ih_user->can('stock_report.view')) {
        $ih_actions[] = [action([\App\Http\Controllers\ReportController::class, 'getStockReport']), __('report.stock_report'), 'chart', false];
    }
    $ih_today = ! empty($shift_day) && $shift_day != now()->format('Y-m-d')
        ? \Carbon::parse($shift_day)->format(session('business.date_format') ?: 'd/m/Y') : __('home.ih_today');
    $ih_can_pl = $ih_user->can('profit_loss_report.view');
    $ih_fig = function ($cls, $label, $icon, $tone, $href, $extra = '') use ($ih_icon) {
        $tag = $href ? 'a' : 'div';
        return '<' . $tag . ' class="ih-fig"' . ($href ? ' href="' . e($href) . '"' : '') . '>'
            . '<span class="ih-fig-ico ih-tone-' . $tone . '">' . $ih_icon($icon) . '</span>'
            . '<span class="ih-fig-txt"><span class="ih-fig-label">' . e($label) . $extra . '</span>'
            . '<span class="ih-fig-val ' . $cls . '"></span></span></' . $tag . '>';
    };
@endphp
<style>
    .ih-band { padding: 18px 20px 26px; }
    .ih-band.has-figs { padding-bottom: 78px; }
    .ih-tabs { display: flex; gap: 6px; margin: 0 0 14px; }
    .ih-tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #fff; background: rgba(255,255,255,.12); text-decoration: none !important; }
    .ih-tab svg { width: 16px; height: 16px; }
    .ih-tab:hover { background: rgba(255,255,255,.22); color: #fff; }
    .ih-tab.is-on { background: #fff; color: #101828; }
    .ih-top { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; justify-content: space-between; }
    .ih-hello { color: #fff; font-size: 28px; line-height: 1.2; font-weight: 700; margin: 0; letter-spacing: -.01em; }
    .ih-date { color: rgba(255,255,255,.8); font-size: 14px; margin-top: 4px; }
    .ih-controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: flex-end; }
    .ih-controls .ih-loc { min-width: 200px; }
    .ih-controls .select2-container .select2-selection--single { height: 40px; border-radius: 10px; border: 0; }
    .ih-controls .select2-container .select2-selection__rendered { line-height: 40px; padding-left: 12px; }
    .ih-controls .select2-container .select2-selection__arrow { height: 38px; }
    .ih-controls select.form-control { height: 40px; border-radius: 10px; border: 0; min-width: 150px; }
    .ih-period-btn { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 14px; border: 0; border-radius: 10px; background: #fff; color: #101828; font-size: 14px; font-weight: 600; white-space: nowrap; }
    .ih-period-btn:hover { background: #f2f4f7; }
    .ih-period-btn svg { width: 18px; height: 18px; color: #475467; }
    .ih-period-btn .ih-caret { width: 15px; height: 15px; }
    .ih-note { margin-top: 14px; border-radius: 10px; background: rgba(255,255,255,.12); color: #fff; padding: 8px 12px; font-size: 13px; }
    .ih-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
    .ih-act { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 8px 14px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none !important;
        color: #fff; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); }
    .ih-act svg { width: 18px; height: 18px; }
    .ih-act:hover { background: rgba(255,255,255,.22); color: #fff; }
    .ih-act.is-primary { background: #fff; color: #101828; border-color: #fff; }
    .ih-act.is-primary:hover { background: #f2f4f7; color: #101828; }
    .ih-wrap { padding: 0 20px; }
    .ih-groups { display: grid; grid-template-columns: 1fr; gap: 16px; margin-top: -56px; position: relative; }
    @media (min-width: 1200px) { .ih-groups { grid-template-columns: 1fr 1fr; } }
    .ih-group { background: #fff; border: 1px solid #eaecf0; border-radius: 16px; box-shadow: 0 4px 14px rgba(16,24,40,.08); overflow: hidden; }
    .ih-group-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 13px 16px; border-bottom: 1px solid #f2f4f7; }
    .ih-group-title { margin: 0; font-size: 15px; font-weight: 700; color: #101828; }
    .ih-period { font-size: 12px; font-weight: 600; color: #475467; background: #f2f4f7; border-radius: 999px; padding: 3px 10px; white-space: nowrap; }
    .ih-figs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ih-fig { display: flex; gap: 12px; align-items: center; padding: 16px; color: #101828; text-decoration: none !important; border-right: 1px solid #f2f4f7; border-bottom: 1px solid #f2f4f7; min-width: 0; }
    .ih-figs .ih-fig:nth-child(2n) { border-right: 0; }
    .ih-figs .ih-fig:nth-last-child(-n+2) { border-bottom: 0; }
    a.ih-fig:hover { background: #f9fafb; color: #101828; }
    .ih-fig-ico { flex: none; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .ih-fig-ico svg { width: 22px; height: 22px; }
    .ih-fig-txt { min-width: 0; display: block; }
    .ih-fig-label { display: block; font-size: 13px; color: #475467; font-weight: 500; white-space: nowrap; overflow: visible; }
    .ih-fig-val { display: block; font-size: 21px; line-height: 1.25; font-weight: 700; color: #101828; margin-top: 2px; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ih-tone-blue { background: #eff8ff; color: #175cd3; } .ih-tone-green { background: #ecfdf3; color: #067647; }
    .ih-tone-amber { background: #fffaeb; color: #b54708; } .ih-tone-red { background: #fef3f2; color: #b42318; }
    .ih-tone-violet { background: #f4f3ff; color: #5925dc; } .ih-tone-teal { background: #f0fdf9; color: #107569; }
    .ih-tone-slate { background: #f2f4f7; color: #344054; }
    .ih-attn { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 16px; }
    .ih-attn-title { font-size: 13px; font-weight: 700; color: #344054; margin-right: 4px; }
    .ih-chip { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px 7px 8px; border-radius: 12px; background: #fff; border: 1px solid #eaecf0; color: #101828; font-size: 13.5px; font-weight: 500;
        text-decoration: none !important; box-shadow: 0 1px 2px rgba(16,24,40,.05); }
    .ih-chip:hover { border-color: #d0d5dd; color: #101828; background: #fcfcfd; }
    .ih-chip .ih-fig-ico { width: 30px; height: 30px; border-radius: 9px; }
    .ih-chip .ih-fig-ico svg { width: 17px; height: 17px; }
    .ih-chip-n { font-weight: 700; font-variant-numeric: tabular-nums; min-width: 1ch; }
    .ih-chip.is-ok { opacity: .75; }
    .ih-chip.is-ok .ih-fig-ico { background: #ecfdf3; color: #067647; }
    .ih-lists > .tw-grid > div { border-radius: 16px; box-shadow: 0 1px 2px rgba(16,24,40,.05) !important; transform: none !important; }
    .ih-lists > .tw-grid > div h3 { font-size: 16px !important; }
    .ih-lists > .tw-grid > div .tw-border-2.tw-rounded-full { border: 0; border-radius: 12px; background: #f2f4f7; width: 38px; height: 38px; }
    .ih-flash { animation: ih-flash 1.4s ease-out 1; }
    @keyframes ih-flash { 0% { box-shadow: 0 0 0 0 rgba(22,163,74,.55); } 100% { box-shadow: 0 0 0 14px rgba(22,163,74,0); } }
    @media (max-width: 767px) {
        .ih-hello { font-size: 22px; } .ih-band { padding: 14px 14px 22px; } .ih-band.has-figs { padding-bottom: 70px; } .ih-wrap { padding: 0 12px; }
        .ih-controls { justify-content: stretch; width: 100%; } .ih-controls > * { flex: 1 1 100%; } .ih-period-btn { justify-content: center; width: 100%; }
        .ih-fig { padding: 12px; gap: 9px; } .ih-fig-ico { width: 34px; height: 34px; border-radius: 10px; } .ih-fig-ico svg { width: 18px; height: 18px; }
        .ih-fig-val { font-size: 16px; } .ih-fig-label { font-size: 12px; white-space: normal; }
    }
</style>

<div class="tw-bg-gradient-to-r tw-from-{{ $ih_tc }}-800 tw-to-{{ $ih_tc }}-900 ih-band {{ $ih_admin ? 'has-figs' : '' }}" id="ih_band">
    {{-- PosInnova: hospital module on = this sales dashboard is the "Pharmacy sales" tab of the hospital home --}}
    @if (in_array('hospital', $ih_mods))
        <div class="ih-tabs" role="tablist">
            <a class="ih-tab" role="tab" aria-selected="false" href="{{ action([\App\Http\Controllers\HomeController::class, 'index']) }}">{!! $ih_icon('hospital') !!} {{ __('clinic.dash_tab_hospital') }}</a>
            <a class="ih-tab is-on" role="tab" aria-selected="true" href="{{ action([\App\Http\Controllers\HomeController::class, 'index'], ['view' => 'sales']) }}">{!! $ih_icon('store') !!} {{ __('clinic.dash_tab_sales') }}</a>
        </div>
    @endif
    <div class="ih-top">
        <div>
            <h1 class="ih-hello">{{ __($ih_greet, ['name' => $ih_name]) }}</h1>
            <div class="ih-date">{{ ucfirst(now()->locale(app()->getLocale())->isoFormat('dddd D MMMM YYYY')) }} · {{ session('business.name') }}</div>
        </div>
        @if ($ih_admin)
            <div class="ih-controls">
                @if (count($all_locations) > 1)
                    <div class="ih-loc">
                        {!! Form::select('dashboard_location', $all_locations, null, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.select_location'), 'id' => 'dashboard_location', 'aria-label' => __('lang_v1.select_location')]) !!}
                    </div>
                @endif
                @if (! empty($shift_enabled))
                    {!! Form::select('dashboard_shift_filter', $shift_filter_options, $current_shift, ['class' => 'form-control', 'id' => 'dashboard_shift_filter', 'aria-label' => __('lang_v1.shift')]) !!}
                @endif
                <button type="button" id="dashboard_date_filter" class="ih-period-btn" title="{{ __('messages.filter_by_date') }}"
                    @if (! empty($shift_day)) data-shift-start="{{ $shift_day }}" data-shift-end="{{ $shift_day }}" @endif>
                    {!! $ih_icon('calendar') !!}<span class="ih-range">{{ $ih_today }}</span>{!! str_replace('<svg ', '<svg class="ih-caret" ', $ih_icon('down')) !!}
                </button>
            </div>
        @endif
    </div>
    @if ($ih_admin && ! empty($shift_enabled))
        {{-- PosInnova hospital shifts: sales are shift-scoped, purchases / expenses are the day's totals. --}}
        <div id="dashboard_shift_scope_note" class="ih-note" data-note-template="@lang('lang_v1.dashboard_shift_scope_note', ['shift' => '__SHIFT__'])" style="display:none;">
            <i class="fa fa-info-circle"></i> <span id="dashboard_shift_scope_text"></span>
        </div>
    @endif
    @if (! empty($ih_actions))
        <div class="ih-actions" aria-label="{{ __('home.ih_quick') }}">
            @foreach ($ih_actions as $a)
                <a href="{{ $a[0] }}" class="ih-act {{ $a[3] ? 'is-primary' : '' }}">{!! $ih_icon($a[2]) !!} {{ $a[1] }}</a>
            @endforeach
        </div>
    @endif
</div>

<div class="ih-wrap">
    @if ($ih_admin)
        <div class="ih-groups">
            <section class="ih-group" id="ih_group_sales" aria-labelledby="ih_sales_title">
                <div class="ih-group-head">
                    <h2 class="ih-group-title" id="ih_sales_title">{{ __('home.ih_sales') }}</h2>
                    <span class="ih-period">{{ $ih_today }}</span>
                </div>
                <div class="ih-figs">
                    {!! $ih_fig('total_sell', __('home.total_sell'), 'cart', 'blue', action([\App\Http\Controllers\SellController::class, 'index'])) !!}
                    {!! $ih_fig('net', __('lang_v1.net'), 'cash', 'green', $ih_can_pl ? action([\App\Http\Controllers\ReportController::class, 'getProfitLoss']) : null,
                        ' <i class="fa fa-info-circle text-info hover-q no-print" aria-hidden="true" data-container="body" data-toggle="popover" data-placement="auto bottom" data-content="' . e(__('lang_v1.net_home_tooltip')) . '" data-html="true" data-trigger="hover"></i>') !!}
                    {!! $ih_fig('invoice_due', __('home.invoice_due'), 'invoice', 'amber', action([\App\Http\Controllers\SellController::class, 'index'])) !!}
                    {!! $ih_fig('total_sell_return', __('lang_v1.total_sell_return'), 'return', 'red', action([\App\Http\Controllers\SellReturnController::class, 'index']),
                        ' <i class="fa fa-info-circle text-info hover-q no-print" aria-hidden="true" data-container="body" data-toggle="popover" data-placement="auto bottom" id="total_srp" data-value="' . e(__('lang_v1.total_sell_return') . '-' . __('lang_v1.total_sell_return_paid')) . '" data-content="" data-html="true" data-trigger="hover"></i>') !!}
                </div>
            </section>
            <section class="ih-group" id="ih_group_out" aria-labelledby="ih_out_title">
                <div class="ih-group-head">
                    <h2 class="ih-group-title" id="ih_out_title">{{ __('home.ih_money_out') }}</h2>
                    <span class="ih-period">{{ $ih_today }}</span>
                </div>
                <div class="ih-figs">
                    {!! $ih_fig('total_purchase', __('home.total_purchase'), 'download', 'teal', action([\App\Http\Controllers\PurchaseController::class, 'index'])) !!}
                    {!! $ih_fig('purchase_due', __('home.purchase_due'), 'alert', 'amber', action([\App\Http\Controllers\PurchaseController::class, 'index'])) !!}
                    {!! $ih_fig('total_purchase_return', __('lang_v1.total_purchase_return'), 'refund', 'violet', action([\App\Http\Controllers\PurchaseReturnController::class, 'index']),
                        ' <i class="fa fa-info-circle text-info hover-q no-print" aria-hidden="true" data-container="body" data-toggle="popover" data-placement="auto bottom" id="total_prp" data-value="' . e(__('lang_v1.total_purchase_return') . '-' . __('lang_v1.total_purchase_return_paid')) . '" data-content="" data-html="true" data-trigger="hover"></i>') !!}
                    {!! $ih_fig('total_expense', __('lang_v1.expense'), 'wallet', 'red', in_array('expenses', $ih_mods) ? action([\App\Http\Controllers\ExpenseController::class, 'index']) : null) !!}
                </div>
            </section>
        </div>
    @endif

    @if ($ih_data)
        {{-- Needs attention: counted from the lists below once they load (no extra queries). --}}
        <div class="ih-attn" id="ih_attn" style="display:none;">
            <span class="ih-attn-title">{{ __('home.ih_attention') }}</span>
            @include('business_review.partials.count_reminder', ['style' => 'chip'])
            @include('bulk_items.partials.home_alert')
            <a href="#stock_alert_table" class="ih-chip" data-ih-count="#stock_alert_table"><span class="ih-fig-ico ih-tone-amber">{!! $ih_icon('package') !!}</span><span class="ih-chip-n">…</span> {{ __('home.ih_low_stock') }}</a>
            <a href="#stock_expiry_alert_table" class="ih-chip" data-ih-count="#stock_expiry_alert_table"><span class="ih-fig-ico ih-tone-red">{!! $ih_icon('clock') !!}</span><span class="ih-chip-n">…</span> {{ __('home.ih_expiring', ['days' => session('business.stock_expiry_alert_days', 30)]) }}</a>
            <a href="#sales_payment_dues_table" class="ih-chip" data-ih-count="#sales_payment_dues_table"><span class="ih-fig-ico ih-tone-blue">{!! $ih_icon('users') !!}</span><span class="ih-chip-n">…</span> {{ __('home.ih_to_collect') }}</a>
            <a href="#purchase_payment_dues_table" class="ih-chip" data-ih-count="#purchase_payment_dues_table"><span class="ih-fig-ico ih-tone-violet">{!! $ih_icon('truck') !!}</span><span class="ih-chip-n">…</span> {{ __('home.ih_to_pay') }}</a>
        </div>
    @endif
</div>
    @if (auth()->user()->can('dashboard.data'))
        <div class="tw-px-5 tw-py-6 ih-lists">
            <div class="tw-grid tw-grid-cols-1 tw-gap-4 sm:tw-gap-5 lg:tw-grid-cols-2">
                @if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view'))
                    @if (!empty($all_locations))
                        <div
                            class="tw-transition-all lg:tw-col-span-2 xl:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                            <div class="tw-p-4 sm:tw-p-5">
                                <div class="tw-flex tw-items-center tw-gap-2.5">
                                    <div
                                        class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                        <svg aria-hidden="true" class="tw-size-5 tw-text-sky-500 tw-shrink-0"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                            <path d="M17 17h-11v-14h-2"></path>
                                            <path d="M6 5l14 1l-1 7h-13"></path>
                                        </svg>
                                    </div>

                                    <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                        {{ __('home.sells_last_30_days') }}
                                    </h3>
                                </div>
                                <div class="tw-mt-5">
                                    <div
                                        class="tw-grid tw-w-full tw-h-100 tw-border tw-border-gray-200 tw-border-dashed tw-rounded-xl tw-bg-gray-50 ">
                                        <p class="tw-text-sm tw-italic tw-font-normal tw-text-gray-400">
                                            {!! $sells_chart_1->container() !!}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- @if (!empty($widgets['after_sales_last_30_days']))
                        @foreach ($widgets['after_sales_last_30_days'] as $widget)
                            {!! $widget !!}
                        @endforeach
                    @endif --}}
                    @if (!empty($all_locations))
                        <div
                            class="tw-transition-all lg:tw-col-span-2 xl:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                            <div class="tw-p-4 sm:tw-p-5">
                                <div class="tw-flex tw-items-center tw-gap-2.5">
                                    <div
                                        class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                        <svg aria-hidden="true" class="tw-size-5 tw-text-sky-500 tw-shrink-0"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                            <path d="M17 17h-11v-14h-2"></path>
                                            <path d="M6 5l14 1l-1 7h-13"></path>
                                        </svg>
                                    </div>
                                    <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                        {{ __('home.sells_current_fy') }}
                                    </h3>
                                </div>
                                <div class="tw-mt-5">
                                    <div
                                        class="tw-grid tw-w-full tw-h-100 tw-border tw-border-gray-200 tw-border-dashed tw-rounded-xl tw-bg-gray-50 ">
                                        <p class="tw-text-sm tw-italic tw-font-normal tw-text-gray-400">
                                            {!! $sells_chart_2->container() !!}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
                {{-- @if (!empty($widgets['after_sales_current_fy']))
                    @foreach ($widgets['after_sales_current_fy'] as $widget)
                        {!! $widget !!}
                    @endforeach
                @endif --}}
                @if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view'))
                    <div
                        class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M12 9v4"></path>
                                        <path
                                            d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z">
                                        </path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            {{ __('lang_v1.sales_payment_dues') }}
                                            @show_tooltip(__('lang_v1.tooltip_sales_payment_dues'))
                                        </h3>
                                    </div>
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        {!! Form::select('sales_payment_dues_location', $all_locations, null, [
                                            'class' => 'form-control select2',
                                            'placeholder' => __('lang_v1.select_location'),
                                            'id' => 'sales_payment_dues_location',
                                        ]) !!}
                                    </div>
                                </div>
                            </div>


                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped" id="sales_payment_dues_table"
                                            data-export-title="{{ __('lang_v1.sales_payment_dues') }}"
                                            style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>@lang('contact.customer')</th>
                                                    <th>@lang('sale.invoice_no')</th>
                                                    <th>@lang('home.due_amount')</th>
                                                    <th class="notexport">@lang('messages.action')</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                @can('purchase.view')
                    <div
                        class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M12 9v4"></path>
                                        <path
                                            d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z">
                                        </path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            {{ __('lang_v1.purchase_payment_dues') }}
                                            @show_tooltip(__('tooltip.payment_dues'))
                                        </h3>
                                    </div>
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        @if (count($all_locations) > 1)
                                            {!! Form::select('purchase_payment_dues_location', $all_locations, null, [
                                                'class' => 'form-control select2 ',
                                                'placeholder' => __('lang_v1.select_location'),
                                                'id' => 'purchase_payment_dues_location',
                                            ]) !!}
                                        @endif
                                    </div>
                                </div>

                            </div>
                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped" id="purchase_payment_dues_table"
                                            data-export-title="{{ __('lang_v1.purchase_payment_dues') }}"
                                            style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>@lang('purchase.supplier')</th>
                                                    <th>@lang('purchase.ref_no')</th>
                                                    <th>@lang('home.due_amount')</th>
                                                    <th class="notexport">@lang('messages.action')</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan
                @can('stock_report.view')
                    <div
                        class="tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                                        <path d="M12 8v4"></path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            {{ __('home.product_stock_alert') }}
                                            @show_tooltip(__('tooltip.product_stock_alert'))
                                        </h3>
                                    </div>
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        @if (count($all_locations) > 1)
                                            {!! Form::select('stock_alert_location', $all_locations, null, [
                                                'class' => 'form-control select2',
                                                'placeholder' => __('lang_v1.select_location'),
                                                'id' => 'stock_alert_location',
                                            ]) !!}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped" id="stock_alert_table"
                                            data-export-title="{{ __('home.product_stock_alert') }}"
                                            style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>@lang('sale.product')</th>
                                                    <th>@lang('business.location')</th>
                                                    <th>@lang('report.current_stock')</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if (session('business.enable_product_expiry') == 1)
                        <div
                            class="tw-transition-all lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                            <div class="tw-p-4 sm:tw-p-5">
                                <div class="tw-flex tw-items-center tw-gap-2.5">
                                    <div
                                        class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                        <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M12 9v4"></path>
                                            <path
                                                d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z">
                                            </path>
                                            <path d="M12 16h.01"></path>
                                        </svg>
                                    </div>
                                    <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                        <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                            <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                                {{ __('home.stock_expiry_alert') }}
                                                @show_tooltip(
                                                __('tooltip.stock_expiry_alert', [
                                                'days'
                                                =>session('business.stock_expiry_alert_days', 30) ]) )
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                    <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                        <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                            <input type="hidden" id="stock_expiry_alert_days"
                                                value="{{ \Carbon::now()->addDays(session('business.stock_expiry_alert_days', 30))->format('Y-m-d') }}">
                                            <table class="table table-bordered table-striped" id="stock_expiry_alert_table"
                                                data-export-title="{{ __('home.stock_expiry_alert') }}">
                                                <thead>
                                                    <tr>
                                                        <th>@lang('business.product')</th>
                                                        <th>@lang('business.location')</th>
                                                        <th>@lang('report.stock_left')</th>
                                                        <th>@lang('product.expires_in')</th>
                                                    </tr>
                                                </thead>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endcan
                @if (auth()->user()->can('so.view_all') || auth()->user()->can('so.view_own'))
                    <div
                        class="tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                                        <path d="M12 8v4"></path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            {{ __('lang_v1.sales_order') }}
                                        </h3>
                                    </div>
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        @if (count($all_locations) > 1)
                                            {!! Form::select('so_location', $all_locations, null, [
                                                'class' => 'form-control select2',
                                                'placeholder' => __('lang_v1.select_location'),
                                                'id' => 'so_location',
                                            ]) !!}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped ajax_view"
                                            data-export-title="{{ __('lang_v1.sales_order') }}"
                                            id="sales_order_table">
                                            <thead>
                                                <tr>
                                                    <th class="notexport">@lang('messages.action')</th>
                                                    <th>@lang('messages.date')</th>
                                                    <th>@lang('restaurant.order_no')</th>
                                                    <th>@lang('sale.customer_name')</th>
                                                    <th>@lang('lang_v1.contact_no')</th>
                                                    <th>@lang('sale.location')</th>
                                                    <th>@lang('sale.status')</th>
                                                    <th>@lang('lang_v1.shipping_status')</th>
                                                    <th>@lang('lang_v1.quantity_remaining')</th>
                                                    <th>@lang('lang_v1.added_by')</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                @if (
                    !empty($common_settings['enable_purchase_requisition']) &&
                        (auth()->user()->can('purchase_requisition.view_all') || auth()->user()->can('purchase_requisition.view_own')))
                    <div
                        class="tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M10 10v4a1 1 0 0 0 1 1h2a1 1 0 0 0 1 -1v-4"></path>
                                        <path d="M9 6h6"></path>
                                        <path d="M10 6v-2a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v2"></path>
                                        <circle cx="12" cy="16" r="2"></circle>
                                        <path d="M5 20h14a2 2 0 0 0 2 -2v-10"></path>
                                        <path d="M15 16v4"></path>
                                        <path d="M9 20v-4"></path>
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            @lang('lang_v1.purchase_requisition')
                                        </h3>
                                    </div>
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        @if (count($all_locations) > 1)
                                            @if (count($all_locations) > 1)
                                                {!! Form::select('pr_location', $all_locations, null, [
                                                    'class' => 'form-control select2',
                                                    'placeholder' => __('lang_v1.select_location'),
                                                    'id' => 'pr_location',
                                                ]) !!}
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped ajax_view"
                                            id="purchase_requisition_table"
                                            data-export-title="{{ __('lang_v1.purchase_requisition') }}"
                                            style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th class="notexport">@lang('messages.action')</th>
                                                    <th>@lang('messages.date')</th>
                                                    <th>@lang('purchase.ref_no')</th>
                                                    <th>@lang('purchase.location')</th>
                                                    <th>@lang('sale.status')</th>
                                                    <th>@lang('lang_v1.required_by_date')</th>
                                                    <th>@lang('lang_v1.added_by')</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if (
                    !empty($common_settings['enable_purchase_order']) &&
                        (auth()->user()->can('purchase_order.view_all') || auth()->user()->can('purchase_order.view_own')))

                    <div
                        class="tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <rect x="4" y="4" width="16" height="16" rx="2" />
                                        <line x1="4" y1="10" x2="20" y2="10" />
                                        <line x1="12" y1="4" x2="12" y2="20" />
                                        <line x1="12" y1="10" x2="16" y2="10" />
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            @lang('lang_v1.purchase_order')
                                        </h3>
                                    </div>
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        @if (count($all_locations) > 1)
                                            {!! Form::select('po_location', $all_locations, null, [
                                                'class' => 'form-control select2',
                                                'placeholder' => __('lang_v1.select_location'),
                                                'id' => 'po_location',
                                            ]) !!}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped ajax_view"
                                            id="purchase_order_table"
                                            data-export-title="{{ __('lang_v1.purchase_order') }}"
                                            style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th class="notexport">@lang('messages.action')</th>
                                                    <th>@lang('messages.date')</th>
                                                    <th>@lang('purchase.ref_no')</th>
                                                    <th>@lang('purchase.location')</th>
                                                    <th>@lang('purchase.supplier')</th>
                                                    <th>@lang('sale.status')</th>
                                                    <th>@lang('lang_v1.quantity_remaining')</th>
                                                    <th>@lang('lang_v1.added_by')</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                @endif
                @if (auth()->user()->can('access_pending_shipments_only') ||
                        auth()->user()->can('access_shipping') ||
                        auth()->user()->can('access_own_shipping'))
                    <div
                        class="tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                        <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                        <path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"></path>
                                        <path d="M3 9l4 0"></path>
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            @lang('lang_v1.pending_shipments')
                                        </h3>
                                    </div>
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        @if (count($all_locations) > 1)
                                            {!! Form::select('pending_shipments_location', $all_locations, null, [
                                                'class' => 'form-control select2 ',
                                                'placeholder' => __('lang_v1.select_location'),
                                                'id' => 'pending_shipments_location',
                                            ]) !!}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped ajax_view" id="shipments_table"
                                            data-export-title="{{ __('lang_v1.pending_shipments') }}">
                                            <thead>
                                                <tr>
                                                    <th class="notexport">@lang('messages.action')</th>
                                                    <th>@lang('messages.date')</th>
                                                    <th>@lang('sale.invoice_no')</th>
                                                    <th>@lang('sale.customer_name')</th>
                                                    <th>@lang('lang_v1.contact_no')</th>
                                                    <th>@lang('sale.location')</th>
                                                    <th>@lang('lang_v1.shipping_status')</th>
                                                    @if (!empty($custom_labels['shipping']['custom_field_1']))
                                                        <th>
                                                            {{ $custom_labels['shipping']['custom_field_1'] }}
                                                        </th>
                                                    @endif
                                                    @if (!empty($custom_labels['shipping']['custom_field_2']))
                                                        <th>
                                                            {{ $custom_labels['shipping']['custom_field_2'] }}
                                                        </th>
                                                    @endif
                                                    @if (!empty($custom_labels['shipping']['custom_field_3']))
                                                        <th>
                                                            {{ $custom_labels['shipping']['custom_field_3'] }}
                                                        </th>
                                                    @endif
                                                    @if (!empty($custom_labels['shipping']['custom_field_4']))
                                                        <th>
                                                            {{ $custom_labels['shipping']['custom_field_4'] }}
                                                        </th>
                                                    @endif
                                                    @if (!empty($custom_labels['shipping']['custom_field_5']))
                                                        <th>
                                                            {{ $custom_labels['shipping']['custom_field_5'] }}
                                                        </th>
                                                    @endif
                                                    <th>@lang('sale.payment_status')</th>
                                                    <th>@lang('restaurant.service_staff')</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                @if (auth()->user()->can('account.access') && config('constants.show_payments_recovered_today') == true)
                    <div
                        class="tw-transition-all lg:tw-col-span-2 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md hover:tw--translate-y-0.5 tw-ring-gray-200">
                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-2.5">
                                <div
                                    class="tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-10 tw-h-10">
                                    <svg aria-hidden="true" class="tw-text-yellow-500 tw-size-5 tw-shrink-0"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor" fill="none" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M12 9v4"></path>
                                        <path
                                            d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z">
                                        </path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                </div>
                                <div class="tw-flex tw-items-center tw-flex-1 tw-min-w-0 tw-gap-1">
                                    <div class="tw-w-full sm:tw-w-1/2 md:tw-w-1/2">
                                        <h3 class="tw-font-bold tw-text-base lg:tw-text-xl">
                                            @lang('lang_v1.payment_recovered_today')
                                        </h3>
                                    </div>

                                </div>
                            </div>
                            <div class="tw-flow-root tw-mt-5  tw-border-gray-200">
                                <div class="tw--mx-4 tw--my-2 tw-overflow-x-auto sm:tw--mx-5">
                                    <div class="tw-inline-block tw-min-w-full tw-py-2 tw-align-middle sm:tw-px-5">
                                        <table class="table table-bordered table-striped" id="cash_flow_table"
                                            data-export-title="{{ __('lang_v1.payment_recovered_today') }}">
                                            <thead>
                                                <tr>
                                                    <th>@lang('messages.date')</th>
                                                    <th>@lang('account.account')</th>
                                                    <th>@lang('lang_v1.description')</th>
                                                    <th>@lang('lang_v1.payment_method')</th>
                                                    <th>@lang('lang_v1.payment_details')</th>
                                                    <th>@lang('account.credit')</th>
                                                    <th>@lang('lang_v1.account_balance')
                                                        @show_tooltip(__('lang_v1.account_balance_tooltip'))</th>
                                                    <th>@lang('lang_v1.total_balance')
                                                        @show_tooltip(__('lang_v1.total_balance_tooltip'))</th>
                                                </tr>
                                            </thead>
                                            <tfoot>
                                                <tr class="bg-gray font-17 footer-total text-center">
                                                    <td colspan="5"><strong>@lang('sale.total'):</strong></td>
                                                    <td class="footer_total_credit"></td>
                                                    <td colspan="2"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                {{-- @if (!empty($widgets['after_dashboard_reports']))
                    @foreach ($widgets['after_dashboard_reports'] as $widget)
                        {!! $widget !!}
                    @endforeach
                @endif --}}
            </div>
        </div>
    @endif

    {{-- modals live inside the section: markup outside it is printed before <!DOCTYPE>, which breaks the <head> --}}
    <div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
    <div class="modal fade edit_pso_status_modal" tabindex="-1" role="dialog"></div>
    <div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
@endsection

@section('css')
    <style>
        .select2-container {
            width: 100% !important;
        }
    </style>
@endsection

@section('javascript')
    <script src="{{ asset('js/home.js?v=' . $asset_v) }}"></script>
    <script>
        // PosInnova home: the period on the figures, and the "Needs attention" counts from the lists.
        $(function () {
            var $f = $('#dashboard_date_filter');
            $f.on('apply.daterangepicker', function (e, p) {
                var t = (p.chosenLabel && p.chosenLabel !== p.locale.customRangeLabel) ? p.chosenLabel
                    : p.startDate.format(moment_date_format) + (p.startDate.isSame(p.endDate, 'day') ? '' : ' ~ ' + p.endDate.format(moment_date_format));
                $f.find('.ih-range').text(t);
                $('.ih-period').text(t);
            });
            var $attn = $('#ih_attn');
            if ($attn.find('#ih_count_due, #ih_bulk_low').length) $attn.show();
            $attn.find('[data-ih-count]').each(function () {
                var $chip = $(this), $t = $($chip.data('ih-count'));
                if (!$t.length) { $chip.remove(); return; }
                $attn.show();
                var fill = function () {
                    if (!$.fn.dataTable.isDataTable($t)) return;
                    var info = $t.DataTable().page.info();
                    if (!info) return;
                    var n = info.recordsDisplay;
                    $chip.find('.ih-chip-n').text(n);
                    $chip.toggleClass('is-ok', n === 0);
                };
                $t.on('draw.dt', fill);
                fill();
            });
            $attn.on('click', '[data-ih-count]', function (e) {
                e.preventDefault();
                var $box = $($(this).data('ih-count')).closest('.tw-rounded-xl');
                if (!$box.length) return;
                $box[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                $box.removeClass('ih-flash');
                setTimeout(function () { $box.addClass('ih-flash'); }, 350);
            });
        });
    </script>
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
    @includeIf('sales_order.common_js')
    @includeIf('purchase_order.common_js')
    @if (!empty($all_locations))
        {!! $sells_chart_1->script() !!}
        {!! $sells_chart_2->script() !!}
    @endif
    <script type="text/javascript">
        $(document).ready(function() {
            sales_order_table = $('#sales_order_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader:false,
                scrollY: "75vh",
                scrollX: true,
                scrollCollapse: true,
                aaSorting: [
                    [1, 'desc']
                ],
                "ajax": {
                    "url": '{{ action([\App\Http\Controllers\SellController::class, 'index']) }}?sale_type=sales_order',
                    "data": function(d) {
                        d.for_dashboard_sales_order = true;

                        if ($('#so_location').length > 0) {
                            d.location_id = $('#so_location').val();
                        }
                    }
                },
                columnDefs: [{
                    "targets": 7,
                    "orderable": false,
                    "searchable": false
                }],
                columns: [{
                        data: 'action',
                        name: 'action'
                    },
                    {
                        data: 'transaction_date',
                        name: 'transaction_date'
                    },
                    {
                        data: 'invoice_no',
                        name: 'invoice_no'
                    },
                    {
                        data: 'conatct_name',
                        name: 'conatct_name'
                    },
                    {
                        data: 'mobile',
                        name: 'contacts.mobile'
                    },
                    {
                        data: 'business_location',
                        name: 'bl.name'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: 'shipping_status',
                        name: 'shipping_status'
                    },
                    {
                        data: 'so_qty_remaining',
                        name: 'so_qty_remaining',
                        "searchable": false
                    },
                    {
                        data: 'added_by',
                        name: 'u.first_name'
                    },
                ]
            });

            @if (auth()->user()->can('account.access') && config('constants.show_payments_recovered_today') == true)

                // Cash Flow Table
                cash_flow_table = $('#cash_flow_table').DataTable({
                    processing: true,
                    serverSide: true,
                    fixedHeader:false,
                    "ajax": {
                        "url": "{{ action([\App\Http\Controllers\AccountController::class, 'cashFlow']) }}",
                        "data": function(d) {
                            d.type = 'credit';
                            d.only_payment_recovered = true;
                        }
                    },
                    "ordering": false,
                    "searching": false,
                    columns: [{
                            data: 'operation_date',
                            name: 'operation_date'
                        },
                        {
                            data: 'account_name',
                            name: 'account_name'
                        },
                        {
                            data: 'sub_type',
                            name: 'sub_type'
                        },
                        {
                            data: 'method',
                            name: 'TP.method'
                        },
                        {
                            data: 'payment_details',
                            name: 'payment_details',
                            searchable: false
                        },
                        {
                            data: 'credit',
                            name: 'amount'
                        },
                        {
                            data: 'balance',
                            name: 'balance'
                        },
                        {
                            data: 'total_balance',
                            name: 'total_balance'
                        },
                    ],
                    "fnDrawCallback": function(oSettings) {
                        __currency_convert_recursively($('#cash_flow_table'));
                    },
                    "footerCallback": function(row, data, start, end, display) {
                        var footer_total_credit = 0;

                        for (var r in data) {
                            footer_total_credit += $(data[r].credit).data('orig-value') ? parseFloat($(
                                data[r].credit).data('orig-value')) : 0;
                        }
                        $('.footer_total_credit').html(__currency_trans_from_en(footer_total_credit));
                    }
                });
            @endif

            $('#so_location').change(function() {
                sales_order_table.ajax.reload();
            });
            @if (!empty($common_settings['enable_purchase_order']))
                //Purchase table
                purchase_order_table = $('#purchase_order_table').DataTable({
                    processing: true,
                    serverSide: true,
                    fixedHeader:false,
                    aaSorting: [
                        [1, 'desc']
                    ],
                    scrollY: "75vh",
                    scrollX: true,
                    scrollCollapse: true,
                    ajax: {
                        url: '{{ action([\App\Http\Controllers\PurchaseOrderController::class, 'index']) }}',
                        data: function(d) {
                            d.from_dashboard = true;

                            if ($('#po_location').length > 0) {
                                d.location_id = $('#po_location').val();
                            }
                        },
                    },
                    columns: [{
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'transaction_date',
                            name: 'transaction_date'
                        },
                        {
                            data: 'ref_no',
                            name: 'ref_no'
                        },
                        {
                            data: 'location_name',
                            name: 'BS.name'
                        },
                        {
                            data: 'name',
                            name: 'contacts.name'
                        },
                        {
                            data: 'status',
                            name: 'transactions.status'
                        },
                        {
                            data: 'po_qty_remaining',
                            name: 'po_qty_remaining',
                            "searchable": false
                        },
                        {
                            data: 'added_by',
                            name: 'u.first_name'
                        }
                    ]
                })

                $('#po_location').change(function() {
                    purchase_order_table.ajax.reload();
                });
            @endif

            @if (!empty($common_settings['enable_purchase_requisition']))
                //Purchase table
                purchase_requisition_table = $('#purchase_requisition_table').DataTable({
                    processing: true,
                    serverSide: true,
                    fixedHeader:false,
                    aaSorting: [
                        [1, 'desc']
                    ],
                    scrollY: "75vh",
                    scrollX: true,
                    scrollCollapse: true,
                    ajax: {
                        url: '{{ action([\App\Http\Controllers\PurchaseRequisitionController::class, 'index']) }}',
                        data: function(d) {
                            d.from_dashboard = true;

                            if ($('#pr_location').length > 0) {
                                d.location_id = $('#pr_location').val();
                            }
                        },
                    },
                    columns: [{
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'transaction_date',
                            name: 'transaction_date'
                        },
                        {
                            data: 'ref_no',
                            name: 'ref_no'
                        },
                        {
                            data: 'location_name',
                            name: 'BS.name'
                        },
                        {
                            data: 'status',
                            name: 'status'
                        },
                        {
                            data: 'delivery_date',
                            name: 'delivery_date'
                        },
                        {
                            data: 'added_by',
                            name: 'u.first_name'
                        },
                    ]
                })

                $('#pr_location').change(function() {
                    purchase_requisition_table.ajax.reload();
                });

                $(document).on('click', 'a.delete-purchase-requisition', function(e) {
                    e.preventDefault();
                    swal({
                        title: LANG.sure,
                        icon: 'warning',
                        buttons: true,
                        dangerMode: true,
                    }).then(willDelete => {
                        if (willDelete) {
                            var href = $(this).attr('href');
                            $.ajax({
                                method: 'DELETE',
                                url: href,
                                dataType: 'json',
                                success: function(result) {
                                    if (result.success == true) {
                                        toastr.success(result.msg);
                                        purchase_requisition_table.ajax.reload();
                                    } else {
                                        toastr.error(result.msg);
                                    }
                                },
                            });
                        }
                    });
                });
            @endif

            sell_table = $('#shipments_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader:false,
                aaSorting: [
                    [1, 'desc']
                ],
                scrollY: "75vh",
                scrollX: true,
                scrollCollapse: true,
                "ajax": {
                    "url": '{{ action([\App\Http\Controllers\SellController::class, 'index']) }}',
                    "data": function(d) {
                        d.only_pending_shipments = true;
                        if ($('#pending_shipments_location').length > 0) {
                            d.location_id = $('#pending_shipments_location').val();
                        }
                    }
                },
                columns: [{
                        data: 'action',
                        name: 'action',
                        searchable: false,
                        orderable: false
                    },
                    {
                        data: 'transaction_date',
                        name: 'transaction_date'
                    },
                    {
                        data: 'invoice_no',
                        name: 'invoice_no'
                    },
                    {
                        data: 'conatct_name',
                        name: 'conatct_name'
                    },
                    {
                        data: 'mobile',
                        name: 'contacts.mobile'
                    },
                    {
                        data: 'business_location',
                        name: 'bl.name'
                    },
                    {
                        data: 'shipping_status',
                        name: 'shipping_status'
                    },
                    @if (!empty($custom_labels['shipping']['custom_field_1']))
                        {
                            data: 'shipping_custom_field_1',
                            name: 'shipping_custom_field_1'
                        },
                    @endif
                    @if (!empty($custom_labels['shipping']['custom_field_2']))
                        {
                            data: 'shipping_custom_field_2',
                            name: 'shipping_custom_field_2'
                        },
                    @endif
                    @if (!empty($custom_labels['shipping']['custom_field_3']))
                        {
                            data: 'shipping_custom_field_3',
                            name: 'shipping_custom_field_3'
                        },
                    @endif
                    @if (!empty($custom_labels['shipping']['custom_field_4']))
                        {
                            data: 'shipping_custom_field_4',
                            name: 'shipping_custom_field_4'
                        },
                    @endif
                    @if (!empty($custom_labels['shipping']['custom_field_5']))
                        {
                            data: 'shipping_custom_field_5',
                            name: 'shipping_custom_field_5'
                        },
                    @endif {
                        data: 'payment_status',
                        name: 'payment_status'
                    },
                    {
                        data: 'waiter',
                        name: 'ss.first_name',
                        @if (empty($is_service_staff_enabled))
                            visible: false
                        @endif
                    }
                ],
                "fnDrawCallback": function(oSettings) {
                    __currency_convert_recursively($('#sell_table'));
                },
                createdRow: function(row, data, dataIndex) {
                    $(row).find('td:eq(4)').attr('class', 'clickable_td');
                }
            });

            $('#pending_shipments_location').change(function() {
                sell_table.ajax.reload();
            });
        });
    </script>
    
@endsection
