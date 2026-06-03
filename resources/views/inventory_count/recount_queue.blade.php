@extends('layouts.app')
@section('title', __('inventory_count.recount_queue'))

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('inventory_count.recount_queue')
        <small class="tw-block tw-text-sm tw-font-normal tw-text-gray-500 tw-mt-1">
            @lang('inventory_count.recount_queue_subtitle')
        </small>
    </h1>
</section>

<section class="content">

    @if(session('status'))
        @php $st = session('status'); @endphp
        <div class="alert alert-{{ ($st['success'] ?? 0) ? 'success' : 'danger' }}">
            {{ $st['msg'] ?? '' }}
        </div>
    @endif

    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        @lang('inventory_count.recount_thresholds_hint', [
            'pct' => (int) config('constants.inventory_count_recount_threshold_pct', 20),
            'value' => number_format((float) config('constants.inventory_count_recount_threshold_value', 10000), 0),
        ])
    </div>

    {{-- ============ LOCATION FILTER ============ --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => __('business.business_location')])
        <form method="GET" action="{{ url('inventory-count/recount-queue') }}" class="form-inline">
            <div class="form-group">
                <label for="rq_location_id" class="tw-mr-2">@lang('business.business_location'):</label>
                {!! Form::select('location_id', $business_locations, $location_id, [
                    'class' => 'form-control select2', 'id' => 'rq_location_id',
                    'placeholder' => __('messages.all'),
                    'style' => 'min-width:260px;',
                    'onchange' => 'this.form.submit()'
                ]) !!}
            </div>
            <a href="{{ url('inventory-count?location_id='.$location_id) }}" class="btn btn-link">
                <i class="fa fa-arrow-left"></i> @lang('inventory_count.inventory_count')
            </a>
        </form>
    @endcomponent

    @component('components.widget', ['class' => 'box-warning', 'title' => __('inventory_count.recount_queue').' ('.$rows->count().')'])

        @if($rows->isEmpty())
            <p class="text-success tw-mb-0"><i class="fa fa-check"></i> @lang('inventory_count.no_recount_items')</p>
        @else
            <p class="text-muted">@lang('inventory_count.select_lines_to_recount')</p>

            <div class="tw-mb-3">
                <button type="button" class="btn btn-primary" id="rq_btn_create" disabled>
                    <i class="fa fa-plus"></i> @lang('inventory_count.create_recount_session')
                </button>
                <button type="button" class="btn btn-danger" id="rq_btn_dismiss" disabled>
                    <i class="fa fa-times"></i> @lang('inventory_count.dismiss_selected')
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-condensed table-bordered" id="rq_table">
                    <thead>
                        <tr>
                            <th style="width:30px;">
                                <input type="checkbox" id="rq_check_all">
                            </th>
                            <th>@lang('inventory_count.sku')</th>
                            <th>@lang('inventory_count.product')</th>
                            <th>@lang('business.business_location')</th>
                            <th>@lang('inventory_count.source_session')</th>
                            <th class="text-right">@lang('inventory_count.theoretical')</th>
                            <th class="text-right">@lang('inventory_count.counted')</th>
                            <th class="text-right">@lang('inventory_count.flagged_diff_qty')</th>
                            <th class="text-right">@lang('inventory_count.flagged_diff_value')</th>
                            <th>@lang('inventory_count.flagged_at')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $r)
                            <tr data-location-id="{{ $r->source_location_id }}">
                                <td>
                                    <input type="checkbox" class="rq_line_check" value="{{ $r->line_id }}" data-location-id="{{ $r->source_location_id }}">
                                </td>
                                <td><code>{{ $r->sku }}</code></td>
                                <td>
                                    {{ $r->product }}
                                    @if($r->product_type === 'variable' && $r->product_variation)
                                        <small class="text-muted">— {{ $r->product_variation }} / {{ $r->variation_name }}</small>
                                    @endif
                                </td>
                                <td>{{ $r->source_location_name }}</td>
                                <td>
                                    <a href="{{ url('inventory-count/'.$r->source_session_id) }}" target="_blank">
                                        {{ $r->source_session_ref }}
                                    </a>
                                </td>
                                <td class="text-right">{{ rtrim(rtrim(number_format((float)$r->theoretical_at_start, 4), '0'), '.') }} {{ $r->unit }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format((float)$r->counted_qty, 4), '0'), '.') }} {{ $r->unit }}</td>
                                <td class="text-right">
                                    <span class="label label-{{ ((float)$r->flagged_diff_qty) >= 0 ? 'success' : 'danger' }}">
                                        {{ ((float)$r->flagged_diff_qty) >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float)$r->flagged_diff_qty, 4), '0'), '.') }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    {{ ((float)$r->flagged_diff_value) >= 0 ? '+' : '' }}{{ number_format((float)$r->flagged_diff_value, 2) }}
                                </td>
                                <td><small>{{ \Carbon\Carbon::parse($r->flagged_at)->format('Y-m-d H:i') }}</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endcomponent
</section>

{{-- Dismiss modal --}}
<div class="modal fade" id="rq_dismiss_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">@lang('inventory_count.dismiss_selected')</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">@lang('inventory_count.dismiss_reason_help')</p>
                <div class="form-group">
                    <label>@lang('inventory_count.dismiss_reason') <span class="text-danger">*</span></label>
                    <textarea id="rq_dismiss_notes" class="form-control" rows="3" maxlength="1000" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                <button type="button" class="btn btn-danger" id="rq_dismiss_confirm">
                    <i class="fa fa-times"></i> @lang('inventory_count.dismiss_selected')
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('javascript')
<script>
$(function () {
    function getSelected() {
        return $('.rq_line_check:checked').map(function () { return $(this).val(); }).get();
    }
    function getSelectedLocations() {
        return $('.rq_line_check:checked').map(function () { return $(this).data('locationId'); }).get();
    }
    function refreshButtons() {
        var n = getSelected().length;
        $('#rq_btn_create, #rq_btn_dismiss').prop('disabled', n === 0);
    }
    $('#rq_check_all').on('change', function () {
        $('.rq_line_check').prop('checked', this.checked);
        refreshButtons();
    });
    $('.rq_line_check').on('change', refreshButtons);

    $('#rq_btn_create').on('click', function () {
        var ids = getSelected();
        if (ids.length === 0) { return; }
        var locs = getSelectedLocations().filter(function (v, i, a) { return a.indexOf(v) === i; });
        if (locs.length > 1) {
            toastr.error(@json(__('inventory_count.recount_single_location_only')));
            return;
        }
        if (! confirm(@json(__('inventory_count.create_recount_session') + '?'))) { return; }
        $.ajax({
            url: '{{ url("inventory-count/recount-queue/create") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', line_ids: ids },
            beforeSend: function () { $('#rq_btn_create').prop('disabled', true); }
        }).done(function (r) {
            if (r.success) {
                toastr.success(r.msg);
                if (r.redirect) { window.location = r.redirect; }
            } else {
                toastr.error(r.msg || 'Error');
                $('#rq_btn_create').prop('disabled', false);
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.msg) || @json(__('messages.something_went_wrong'));
            toastr.error(msg);
            $('#rq_btn_create').prop('disabled', false);
        });
    });

    $('#rq_btn_dismiss').on('click', function () {
        if (getSelected().length === 0) { return; }
        $('#rq_dismiss_notes').val('');
        $('#rq_dismiss_modal').modal('show');
    });

    $('#rq_dismiss_confirm').on('click', function () {
        var notes = $.trim($('#rq_dismiss_notes').val());
        if (notes.length === 0) {
            $('#rq_dismiss_notes').focus();
            return;
        }
        var ids = getSelected();
        $.ajax({
            url: '{{ url("inventory-count/recount-queue/dismiss") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', line_ids: ids, resolution_notes: notes },
            beforeSend: function () { $('#rq_dismiss_confirm').prop('disabled', true); }
        }).done(function (r) {
            $('#rq_dismiss_modal').modal('hide');
            if (r.success) {
                toastr.success(r.msg);
                setTimeout(function () { window.location.reload(); }, 600);
            } else {
                toastr.error(r.msg || 'Error');
                $('#rq_dismiss_confirm').prop('disabled', false);
            }
        }).fail(function () {
            toastr.error(@json(__('messages.something_went_wrong')));
            $('#rq_dismiss_confirm').prop('disabled', false);
        });
    });
});
</script>
@endsection
