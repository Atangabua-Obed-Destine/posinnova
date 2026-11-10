{{-- PosInnova: the cashier's cash register, opened and closed from the clinic screens
     (before, only the POS screen had these buttons, which a clinic cashier may not open). --}}
@if (auth()->user()->can('sell.payments') || auth()->user()->can('view_cash_register') || auth()->user()->can('close_cash_register'))
    @php $reg_open = \App\Utils\ClinicCashier::openRegister(auth()->user()->id); @endphp
    <div id="cash_register_bar" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 14px;margin-bottom:14px;">
        @if ($reg_open)
            <span><i class="fas fa-cash-register text-success"></i>
                <strong>{{ __('clinic.reg_open_since', ['time' => \Carbon::parse($reg_open->created_at)->format('H:i')]) }}</strong></span>
            <span style="flex:1;"></span>
            @can('view_cash_register')
                <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline btn-modal" data-container=".register_details_modal"
                    data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getRegisterDetails']) }}"><i class="fas fa-briefcase"></i> @lang('clinic.reg_details')</button>
            @endcan
            @can('close_cash_register')
                <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-error btn-modal" data-container=".close_register_modal"
                    data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getCloseRegister']) }}"><i class="fas fa-lock"></i> @lang('clinic.reg_close')</button>
            @endcan
        @else
            <span><i class="fas fa-cash-register text-warning"></i> <strong>@lang('clinic.reg_closed')</strong>
                <span class="text-muted">@lang('clinic.reg_closed_help')</span></span>
            <span style="flex:1;"></span>
            <a href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'create'], ['return_to' => 'accounts']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-success tw-text-white" id="open_register_btn"><i class="fas fa-unlock"></i> @lang('clinic.reg_open')</a>
        @endif
    </div>
    <div class="modal fade register_details_modal" tabindex="-1" role="dialog"></div>
    <div class="modal fade close_register_modal" tabindex="-1" role="dialog"></div>
@endif
