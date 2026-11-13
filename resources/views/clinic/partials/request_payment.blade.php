{{--
    PosInnova: the payment of a lab / imaging request's fee, where the work is done.
    $pay  ClinicRequestPayment::of($req)
    $req  the LabRequest / ImagingRequest
    $ack  true on the result / report entry form: an unpaid request asks the user to choose to go on
--}}
@php
    $pu = new \App\Utils\Util();
    $tone = ['paid' => ['#f0fdf4', '#bbf7d0', '#166534', 'fa-check-circle'], 'partial' => ['#fffbeb', '#fde68a', '#92400e', 'fa-exclamation-triangle'],
        'due' => ['#fef2f2', '#fecaca', '#991b1b', 'fa-exclamation-circle'], 'none' => ['#f8fafc', '#e2e8f0', '#475569', 'fa-info-circle'],
        'not_billed' => ['#f8fafc', '#e2e8f0', '#475569', 'fa-info-circle']][$pay['state']] ?? ['#f8fafc', '#e2e8f0', '#475569', 'fa-info-circle'];
    $fmt = fn ($n) => $pu->num_f($n, true);
    $text = [
        'paid' => __('clinic.pay_panel_paid', ['invoice' => $pay['invoice_no'], 'total' => $fmt($pay['total'])]),
        'partial' => __('clinic.pay_panel_partial', ['paid' => $fmt($pay['paid']), 'total' => $fmt($pay['total']), 'invoice' => $pay['invoice_no'], 'due' => $fmt($pay['due'])]),
        'due' => __('clinic.pay_panel_due', ['due' => $fmt($pay['due']), 'invoice' => $pay['invoice_no']]),
        'none' => __('clinic.pay_panel_none'),
        'not_billed' => __('clinic.pay_panel_not_billed'),
    ][$pay['state']] ?? '';
    // asked once per request: after someone chose to go on, that choice (shown below) covers later saves
    $needs = \App\Utils\ClinicRequestPayment::needsDecision($pay) && empty($req->unpaid_ack_at);
@endphp
<div class="req-pay" data-state="{{ $pay['state'] }}" style="background:{{ $tone[0] }}; border:1px solid {{ $tone[1] }}; color:{{ $tone[2] }}; border-radius:10px; padding:10px 14px; margin-bottom:14px;">
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <i class="fas {{ $tone[3] }}"></i>
        <strong>@lang('clinic.pay_col'):</strong>
        <span class="req-pay-text">{{ $text }}</span>
        @if (in_array($pay['state'], ['paid', 'partial', 'due']) && ! empty($pay['contact_id']))
            <a href="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$pay['contact_id']]) }}" style="margin-left:auto;" class="tw-text-sm">
                <i class="fas fa-wallet"></i> @lang('clinic.pay_open_account')
            </a>
        @endif
    </div>
    @if (! empty($req->unpaid_ack_at))
        @php $ack_user = \App\User::where('id', $req->unpaid_ack_by)->first(); @endphp
        <div class="req-pay-record" style="margin-top:6px; font-size:13px; color:#92400e;">
            <i class="fas fa-user-check"></i>
            {{ __('clinic.pay_ack_record', ['user' => trim(optional($ack_user)->user_full_name) ?: '-', 'date' => @format_datetime($req->unpaid_ack_at), 'due' => $fmt($req->unpaid_ack_due)]) }}
            @if ($req->unpaid_ack_reason) {{ __('clinic.pay_ack_record_reason', ['reason' => $req->unpaid_ack_reason]) }} @endif
        </div>
    @endif
    @if (! empty($req->unpaid_print_at))
        @php $print_user = \App\User::where('id', $req->unpaid_print_by)->first(); @endphp
        <div class="req-pay-print-record" style="margin-top:6px; font-size:13px; color:#92400e;">
            <i class="fas fa-print"></i>
            {{ __('clinic.pay_print_record', ['user' => trim(optional($print_user)->user_full_name) ?: '-', 'date' => @format_datetime($req->unpaid_print_at), 'due' => $fmt($req->unpaid_print_due)]) }}
            @if ($req->unpaid_print_reason) {{ __('clinic.pay_ack_record_reason', ['reason' => $req->unpaid_print_reason]) }} @endif
        </div>
    @endif
    @if (! empty($ack) && $needs)
        <div class="req-pay-ack" style="margin-top:10px; background:#fff; border:1px dashed {{ $tone[1] }}; border-radius:8px; padding:8px 12px; color:#0f172a;">
            <label style="display:flex; gap:8px; align-items:flex-start; font-weight:600; margin:0; cursor:pointer;">
                {{-- always starts unticked (never filled back in, nor restored by the browser) --}}
                <input type="checkbox" name="unpaid_ack" value="1" class="req-pay-ack-box" style="margin-top:3px;" autocomplete="off">
                <span>{{ __('clinic.pay_ack_label', ['due' => $fmt($pay['due'])]) }}</span>
            </label>
            <input type="text" name="unpaid_reason" maxlength="191" class="form-control input-sm" style="margin-top:6px;"
                value="{{ old('unpaid_reason') }}" placeholder="{{ __('clinic.pay_ack_reason') }}: {{ __('clinic.pay_ack_reason_ph') }}">
        </div>
        <script>
            // the form does not go without the choice (the server checks it too)
            (function () {
                var box = document.currentScript.parentNode.querySelector('.req-pay-ack-box');
                // a page restored from the browser's back/forward memory keeps no tick either
                window.addEventListener('pageshow', function () { if (box) { box.checked = false; } });
            })();
            document.addEventListener('submit', function (e) {
                var form = e.target, box = form && form.querySelector ? form.querySelector('.req-pay-ack-box') : null;
                if (!box || box.checked) { return; }
                e.preventDefault();
                e.stopImmediatePropagation();
                var wrap = box.closest('.req-pay-ack');
                wrap.style.borderColor = '#dc2626';
                wrap.style.boxShadow = '0 0 0 3px rgba(220,38,38,.15)';
                wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (window.toastr) { toastr.warning(@json(__('clinic.pay_ack_required', ['due' => $fmt($pay['due'])]))); }
            }, true);
        </script>
    @endif
</div>
