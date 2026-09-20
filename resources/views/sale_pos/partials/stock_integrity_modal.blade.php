{{--
    PosInnova Stock Doctor modal + logic.
    Detects "ghost stock" (displayed qty > sellable/backed qty) which blocks POS
    sales with "Mismatch between sold and purchase quantity", lists the affected
    products, and heals them in one click (or per-product). Healing back-fills a
    purchase line for the gap and NEVER changes the displayed quantity.
--}}
<div class="modal fade" id="stock_doctor_modal" tabindex="-1" role="dialog" aria-labelledby="stockDoctorLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="stockDoctorLabel">
                    <i class="fa fa-stethoscope tw-text-[#EF4B53]"></i> @lang('lang_v1.stock_doctor')
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="margin-top:-6px;">
                    @lang('lang_v1.stock_doctor_help')
                </p>

                <div id="stock_doctor_empty" class="text-center text-success" style="display:none; padding:24px;">
                    <i class="fa fa-check-circle" style="font-size:36px;"></i>
                    <p style="margin-top:8px;">@lang('lang_v1.stock_doctor_all_clear')</p>
                </div>

                <div id="stock_doctor_content" style="display:none;">
                    <div class="table-responsive" style="max-height:340px; overflow:auto;">
                        <table class="table table-condensed table-bordered">
                            <thead>
                                <tr>
                                    <th>@lang('product.sku')</th>
                                    <th>@lang('product.product')</th>
                                    <th>@lang('business.location')</th>
                                    <th class="text-right">@lang('lang_v1.displayed')</th>
                                    <th class="text-right">@lang('lang_v1.sellable')</th>
                                    <th class="text-right">@lang('lang_v1.gap')</th>
                                    <th class="text-center">@lang('messages.action')</th>
                                </tr>
                            </thead>
                            <tbody id="stock_doctor_rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span id="stock_doctor_summary" class="pull-left text-muted" style="line-height:34px;"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                <button type="button" class="btn btn-primary" id="stock_doctor_heal_all">
                    <i class="fa fa-magic"></i> @lang('lang_v1.stock_doctor_heal_all')
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    // jQuery is loaded near the end of the page (after @yield('content')), so
    // defer this whole block until jQuery is available, then run on DOM ready.
    (function bootStockDoctor() {
        if (typeof window.jQuery === 'undefined') {
            return window.setTimeout(bootStockDoctor, 50);
        }
        window.jQuery(function ($) {
        var scanUrl = "{{ route('stock_integrity.scan') }}";
        var healUrl = "{{ route('stock_integrity.heal') }}";
        var csrf = "{{ csrf_token() }}";
        var num_f = function (n) { return parseFloat(n).toString(); };

        function renderRows(rows) {
            var $tbody = $('#stock_doctor_rows').empty();
            if (!rows.length) {
                $('#stock_doctor_content').hide();
                $('#stock_doctor_empty').show();
                $('#stock_doctor_heal_all').prop('disabled', true);
                $('#stock_doctor_summary').text('');
                return;
            }
            $('#stock_doctor_empty').hide();
            $('#stock_doctor_content').show();
            $('#stock_doctor_heal_all').prop('disabled', false);

            var totalGap = 0;
            $.each(rows, function (i, r) {
                totalGap += parseFloat(r.gap);
                var $tr = $('<tr/>');
                $tr.append($('<td/>').text(r.sub_sku || ''));
                $tr.append($('<td/>').text(r.product || ''));
                $tr.append($('<td/>').text(r.location_name || ''));
                $tr.append($('<td class="text-right"/>').text(num_f(r.display)));
                $tr.append($('<td class="text-right text-danger"/>').text(num_f(r.sellable)));
                $tr.append($('<td class="text-right"/>').append($('<strong/>').text(num_f(r.gap))));
                var $btn = $('<button type="button" class="btn btn-xs btn-primary stock-doctor-heal-one"><i class="fa fa-magic"></i> @lang('lang_v1.stock_doctor_heal')</button>')
                    .attr('data-variation_id', r.variation_id);
                $tr.append($('<td class="text-center"/>').append($btn));
                $tbody.append($tr);
            });

            $('#stock_doctor_summary').text(
                "{{ __('lang_v1.stock_doctor_summary', ['count' => ':C', 'units' => ':U']) }}"
                    .replace(':C', rows.length).replace(':U', num_f(totalGap.toFixed(4)))
            );
        }

        function scan(showToastWhenClean) {
            $.get(scanUrl).done(function (resp) {
                if (!resp || resp.success != 1) { return; }
                var count = resp.count || 0;
                if (count > 0) {
                    $('#stock_doctor_badge').text(count);
                    $('#stock_doctor_btn').css('display', 'inline-flex');
                } else {
                    $('#stock_doctor_btn').css('display', 'none');
                    if (showToastWhenClean && typeof toastr !== 'undefined') {
                        toastr.success("{{ __('lang_v1.stock_doctor_all_clear') }}");
                    }
                }
                renderRows(resp.rows || []);
            }).fail(function (xhr) {
                if (window.console) { console.warn('Stock Doctor scan failed:', xhr.status); }
            });
        }

        // Initial silent scan on POS load.
        scan(false);

        // Open modal.
        $(document).on('click', '#stock_doctor_btn', function () {
            $('#stock_doctor_modal').modal('show');
            scan(false);
        });

        function heal(variationIds, $btn) {
            var payload = { _token: csrf };
            if (variationIds && variationIds.length) { payload.variation_ids = variationIds; }
            if ($btn) { $btn.prop('disabled', true); }
            $.post(healUrl, payload).done(function (resp) {
                if (typeof toastr !== 'undefined') {
                    if (resp.success == 1) { toastr.success(resp.msg); }
                    else { toastr.error(resp.msg); }
                }
                scan(false);
            }).fail(function () {
                if (typeof toastr !== 'undefined') { toastr.error("{{ __('messages.something_went_wrong') }}"); }
            }).always(function () {
                if ($btn) { $btn.prop('disabled', false); }
            });
        }

        // Heal all.
        $(document).on('click', '#stock_doctor_heal_all', function () {
            heal([], $(this));
        });

        // Heal one product.
        $(document).on('click', '.stock-doctor-heal-one', function () {
            var vid = $(this).attr('data-variation_id');
            heal([vid], $(this));
        });
        });
    })();
</script>
