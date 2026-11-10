@extends('layouts.app')

@section('title', __('sale.pos_sale'))

@section('content')
    <section class="content no-print">
        <input type="hidden" id="amount_rounding_method" value="{{ $pos_settings['amount_rounding_method'] ?? '' }}">
        @if (!empty($pos_settings['allow_overselling']))
            <input type="hidden" id="is_overselling_allowed">
        @endif
        @if (!empty($pos_settings['enable_multi_location_sale']))
            <input type="hidden" id="multi_location_sale_enabled" value="1">
        @endif
        @if (session('business.enable_rp') == 1)
            <input type="hidden" id="reward_point_enabled">
        @endif
        @php
            $is_discount_enabled = $pos_settings['disable_discount'] != 1 ? true : false;
            $is_rp_enabled = session('business.enable_rp') == 1 ? true : false;
        @endphp
        {!! Form::open([
            'url' => action([\App\Http\Controllers\SellPosController::class, 'store']),
            'method' => 'post',
            'id' => 'add_pos_sell_form',
        ]) !!}
        {{-- Same condition as the product pane below: no pane, nothing to collapse. --}}
        @if (empty($pos_settings['hide_product_suggestion']) && !isMobile())
            @include('sale_pos.partials.pos_browser_toggle')
        @endif
        <div class="row mb-12">
            <div class="col-md-12 tw-pt-0 tw-mb-14">
                <div class="row tw-flex lg:tw-flex-row md:tw-flex-col sm:tw-flex-col tw-flex-col tw-items-start md:tw-gap-4">
                    {{-- <div class="@if (empty($pos_settings['hide_product_suggestion'])) col-md-7 @else col-md-10 col-md-offset-1 @endif no-padding pr-12"> --}}
                    <div class="pi-pos-cart-pane tw-px-3 tw-w-full  lg:tw-px-0 lg:tw-pr-0 @if(empty($pos_settings['hide_product_suggestion'])) lg:tw-w-[60%]  @else lg:tw-w-[100%] @endif">

                        <div class="tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-rounded-2xl tw-bg-white tw-mb-2 md:tw-mb-8 tw-p-2">

                            {{-- <div class="box box-solid mb-12 @if (!isMobile()) mb-40 @endif"> --}}
                                <div class="box-body pb-0">
                                    {!! Form::hidden('location_id', $default_location->id ?? null, [
                                        'id' => 'location_id',
                                        'data-receipt_printer_type' => !empty($default_location->receipt_printer_type)
                                            ? $default_location->receipt_printer_type
                                            : 'browser',
                                        'data-default_payment_accounts' => $default_location->default_payment_accounts ?? '',
                                    ]) !!}
                                    <!-- sub_type -->
                                    {!! Form::hidden('sub_type', isset($sub_type) ? $sub_type : null) !!}
                                    {{-- Hospital module: stamped when dispensing a consultation's prescription --}}
                                    {!! Form::hidden('prescription_consultation_id', !empty($prescription_data) ? $prescription_data['consultation_id'] : null, ['id' => 'prescription_consultation_id']) !!}
                                    {{-- Hospital module: stamped when dispensing to an admitted patient (charges the running bill) --}}
                                    {!! Form::hidden('ward_dispense_admission_id', !empty($prescription_data) ? ($prescription_data['admission_id'] ?? null) : null, ['id' => 'ward_dispense_admission_id']) !!}
                                    <input type="hidden" id="item_addition_method"
                                        value="{{ $business_details->item_addition_method }}">
                                    @include('sale_pos.partials.pos_form')

                                    @include('sale_pos.partials.pos_form_totals')

                                    @include('sale_pos.partials.payment_modal')

                                    @include('sale_pos.partials.invoice_preview_modal')

                                    @include('sale_pos.partials.change_base_unit_modal')

                                    {{-- PosInnova: pay_contact_due_modal moved outside the outer POS form (see end of section) so the AJAX-loaded inner <form id="pay_contact_due_form"> isn't stripped by the HTML5 nested-form rule. --}}

                                    @if (empty($pos_settings['disable_suspend']))
                                        @include('sale_pos.partials.suspend_note_modal')
                                    @endif

                                    @if (empty($pos_settings['disable_recurring_invoice']))
                                        @include('sale_pos.partials.recurring_invoice_modal')
                                    @endif
                                </div>
                            {{-- </div> --}}
                        </div>
                    </div>
                    @if (empty($pos_settings['hide_product_suggestion']) && !isMobile())
                        <div class="pi-pos-browser-pane md:tw-no-padding tw-w-full lg:tw-w-[40%] tw-px-5" id="pi_pos_browser_pane">
                            @include('sale_pos.partials.pos_sidebar')
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @include('sale_pos.partials.pos_form_actions')
        {!! Form::close() !!}
    </section>

    <!-- This will be printed -->
    <section class="invoice print_section" id="receipt_section">
    </section>
    <div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        @include('contact.create', ['quick_add' => true])
    </div>
    {{-- PosInnova: pay_contact_due_modal must live outside the POS form so its AJAX-injected inner <form> isn't stripped (HTML5 nested-form rule). --}}
    <div class="modal fade pay_contact_due_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
    @if (empty($pos_settings['hide_product_suggestion']) && isMobile())
        @include('sale_pos.partials.mobile_product_suggestions')
    @endif
    <!-- /.content -->
    <div class="modal fade register_details_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
    <div class="modal fade close_register_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
    <!-- quick product modal -->
    <div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>

    <div class="modal fade" id="expense_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

    @include('sale_pos.partials.configure_search_modal')

    @include('sale_pos.partials.recent_transactions_modal')

    @include('sale_pos.partials.weighing_scale_modal')

    @include('sale_pos.partials.stock_integrity_modal')

    @if (!empty($pos_settings['enable_multi_location_sale']))
        <div class="modal fade" id="select_sale_location_modal" tabindex="-1" role="dialog" aria-labelledby="selectSaleLocationLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="selectSaleLocationLabel"><i class="fa fa-map-marker"></i> @lang('lang_v1.select_sale_location')</h4>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">@lang('lang_v1.select_sale_location_help')</p>
                        <h5 id="select_sale_location_product_name" style="margin-top:0;font-weight:600;"></h5>
                        <div id="select_sale_location_list" class="list-group" style="margin-top:10px;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($shift_enabled))
        {{-- PosInnova hospital shifts: shift handover prompt --}}
        <div class="modal fade" id="shift_handover_modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="fa fa-clock"></i> @lang('lang_v1.shift_ended_title')</h4>
                    </div>
                    <div class="modal-body text-center">
                        <p id="shift_handover_message">@lang('lang_v1.shift_ended_message')</p>
                        <p class="text-muted" style="font-size:12px;margin-top:8px;">
                            <i class="fa fa-info-circle"></i> @lang('lang_v1.shift_handover_personal_note')
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" id="shift_close_btn" data-dismiss="modal">@lang('messages.close')</button>
                        <button type="button" class="btn btn-default" id="shift_extend_btn" data-dismiss="modal">@lang('lang_v1.extend_current_shift')</button>
                        <button type="button" class="btn btn-primary" id="shift_start_next_btn">@lang('lang_v1.start_next_shift')</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

@stop
@section('css')
    <!-- include module css -->
    @if (!empty($pos_module_data))
        @foreach ($pos_module_data as $key => $value)
            @if (!empty($value['module_css_path']))
                @includeIf($value['module_css_path'])
            @endif
        @endforeach
    @endif
@stop
@section('javascript')
    <script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/printer.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
    @include('sale_pos.partials.keyboard_shortcuts')

    <!-- Call restaurant module if defined -->
    @if (in_array('tables', $enabled_modules) ||
            in_array('modifiers', $enabled_modules) ||
            in_array('service_staff', $enabled_modules))
        <script src="{{ asset('js/restaurant.js?v=' . $asset_v) }}"></script>
    @endif
    <!-- include module js -->
    @if (!empty($pos_module_data))
        @foreach ($pos_module_data as $key => $value)
            @if (!empty($value['module_js_path']))
                @includeIf($value['module_js_path'], ['view_data' => $value['view_data']])
            @endif
        @endforeach
    @endif

    @if (!empty($shift_enabled))
        {{-- PosInnova hospital shifts: countdown + non-blocking handover prompt --}}
        <script>
            $(function () {
                var $badge = $('#pos_shift_badge');
                if (!$badge.length) return;

                var endsAtStr = $badge.data('ends-at');
                var serverNowStr = $badge.data('server-now');
                var extended = parseInt($badge.data('extended') || 0, 10) === 1;

                // Anchor the countdown to server time, immune to a wrong PC clock.
                var clientOffset = serverNowStr ? (new Date(serverNowStr).getTime() - Date.now()) : 0;
                var endsAt = endsAtStr ? new Date(endsAtStr).getTime() : null;
                var $modal = $('#shift_handover_modal');
                var promptOpen = false;
                var snoozeUntil = 0;

                var MSG_STILL_ACTIVE = @json(__('lang_v1.shift_still_active'));
                var MSG_EXTENDED_PROMPT = @json(__('lang_v1.shift_extended_prompt'));
                var MSG_ENDED = @json(__('lang_v1.shift_ended_message'));
                var REMIND_MS = 120000; // re-remind every 2 min while extended / shift over

                function serverNow() { return Date.now() + clientOffset; }
                function two(n) { return (n < 10 ? '0' : '') + n; }

                // Non-blocking: never pop over an active checkout.
                function saleInProgress() {
                    return $('#pos_table tbody tr').length > 0 ||
                        $('.modal:visible').not('#shift_handover_modal').length > 0;
                }

                function setCountdown(text) { $('#pos_shift_countdown').text('· ' + text); }

                // A cashier may only start the next shift once the current one's
                // time is actually up, or after they've extended it. Before that,
                // switching is blocked (the server enforces the same rule).
                function shiftIsOver() { return endsAt !== null && serverNow() >= endsAt; }

                // Tailor the prompt to the current state: forbid an early switch,
                // offer extend only at the boundary, allow closing out an extension.
                function configureModal() {
                    var over = shiftIsOver();
                    var canNext = extended || over;
                    $('#shift_start_next_btn').toggle(canNext);
                    $('#shift_extend_btn').toggle(over && !extended);
                    $('#shift_close_btn').toggle(!canNext || extended);
                    if (!canNext) {
                        $('#shift_handover_message').text(MSG_STILL_ACTIVE);
                    } else if (extended) {
                        $('#shift_handover_message').text(MSG_EXTENDED_PROMPT);
                    } else {
                        $('#shift_handover_message').text(MSG_ENDED);
                    }
                }

                // Eligible to auto-remind when the shift is over, OR when extended
                // (so a cashier who extended is nudged on load + every 2 min and
                // doesn't forget to move on). An active, un-extended shift never
                // auto-prompts — no early switching.
                function shouldAutoPrompt() { return extended || shiftIsOver(); }

                function maybePrompt() {
                    if (promptOpen) return;
                    if (!shouldAutoPrompt()) return;
                    if (serverNow() < snoozeUntil) return;
                    if (saleInProgress()) { snoozeUntil = serverNow() + 20000; return; }
                    promptOpen = true;
                    configureModal();
                    $modal.modal('show');
                }

                $modal.on('hidden.bs.modal', function () {
                    promptOpen = false;
                    // Keep nudging: re-show in REMIND_MS whether the shift is over
                    // and undecided, or extended and not yet rolled over.
                    snoozeUntil = serverNow() + REMIND_MS;
                });

                function tick() {
                    if (!endsAt) return;
                    if (extended) { setCountdown(@json(__('lang_v1.extended'))); maybePrompt(); return; }

                    var remaining = Math.floor((endsAt - serverNow()) / 1000);
                    if (remaining <= 0) {
                        setCountdown(@json(__('lang_v1.shift_over')));
                        maybePrompt();
                        return;
                    }
                    var h = Math.floor(remaining / 3600);
                    var m = Math.floor((remaining % 3600) / 60);
                    var s = remaining % 60;
                    setCountdown((h > 0 ? h + ':' : '') + two(m) + ':' + two(s));
                }

                tick();
                setInterval(tick, 1000);

                function csrf() {
                    return $('input[name=_token]').first().val() ||
                        $('meta[name=csrf-token]').attr('content') || '';
                }

                // The badge doubles as a button: click it anytime to reopen the
                // handover prompt — e.g. after extending, to end the shift and
                // start the next one whenever the cashier is actually done.
                $badge.css('cursor', 'pointer')
                    .attr('title', @json(__('lang_v1.end_shift_start_next')))
                    .on('click', function () { configureModal(); $modal.modal('show'); });

                $('#shift_extend_btn').on('click', function () {
                    $.ajax({
                        method: 'POST', url: '/cash-register/extend-shift', dataType: 'json',
                        data: { _token: csrf() },
                        success: function (resp) {
                            extended = true;
                            setCountdown(@json(__('lang_v1.extended')));
                            if (typeof toastr !== 'undefined' && resp && resp.msg) toastr.success(resp.msg);
                        }
                    });
                });

                $('#shift_start_next_btn').on('click', function () {
                    var $b = $(this).prop('disabled', true);
                    $.ajax({
                        method: 'POST', url: '/cash-register/start-next-shift', dataType: 'json',
                        data: { _token: csrf() },
                        success: function (resp) {
                            if (resp && resp.success) {
                                if (typeof toastr !== 'undefined' && resp.msg) toastr.success(resp.msg);
                                setTimeout(function () { window.location.reload(); }, 700);
                            } else {
                                if (typeof toastr !== 'undefined') toastr.error(resp && resp.msg ? resp.msg : 'Error');
                                $b.prop('disabled', false);
                            }
                        },
                        error: function () {
                            if (typeof toastr !== 'undefined') toastr.error(@json(__('messages.something_went_wrong')));
                            $b.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
    @endif

    @if (!empty($prescription_data))
        {{-- Hospital module: pre-fill the POS from a consultation's prescription --}}
        <script>
            $(function () {
                var rx = @json($prescription_data);

                // Lock the sale to the patient: append the option and select it.
                if (rx.contact_id && rx.patient_name) {
                    var $cust = $('select#customer_id');
                    if ($cust.find('option[value="' + rx.contact_id + '"]').length === 0) {
                        $cust.append(new Option(rx.patient_name, rx.contact_id, true, true));
                    }
                    $cust.val(rx.contact_id).trigger('change');
                }

                // Ward dispense: stamp the admission and inform the cashier the
                // charge goes to the patient's running bill (no specific lines).
                if (rx.mode === 'ward_dispense') {
                    $('#ward_dispense_admission_id').val(rx.admission_id || '');
                    if (typeof toastr !== 'undefined') {
                        toastr.info(@json(__('lang_v1.ward_dispensing_for')) + ' ' + rx.patient_name);
                    }
                    return; // no auto lines — the nurse adds items manually
                }

                // Add each prescribed line to the cart with its quantity.
                // pos_product_row(variation_id, purchase_line_id, weighing_barcode, quantity)
                if (rx.lines && rx.lines.length) {
                    var i = 0;
                    function addNext() {
                        if (i >= rx.lines.length) return;
                        var line = rx.lines[i++];
                        if (typeof pos_product_row === 'function') {
                            pos_product_row(line.variation_id, null, null, line.quantity || 1);
                        }
                        // Stagger to let each AJAX row finish before the next.
                        setTimeout(addNext, 500);
                    }
                    addNext();

                    if (typeof toastr !== 'undefined') {
                        toastr.info(@json(__('lang_v1.dispensing_prescription_for')) + ' ' + rx.patient_name);
                    }
                } else if (typeof toastr !== 'undefined') {
                    toastr.warning(@json(__('clinic.rx_nothing_waiting')));
                }
            });
        </script>
    @endif
@endsection
