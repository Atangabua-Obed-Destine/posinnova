@extends('layouts.app')
@section('title', __('clinic.doctor_queue'))

@section('css')
<style>
    .dq-row { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 8px; }
    .dq-row.is-with { border-color: #f59e0b; background: #fffbeb; }
    .dq-no { font-size: 30px; font-weight: 800; color: #1d4ed8; min-width: 54px; text-align: center; line-height: 1; }
    .dq-main { flex: 1; min-width: 0; }
    .dq-name { font-weight: 700; font-size: 15px; color: #0f172a; }
    .dq-meta { color: #64748b; font-size: 12px; }
    .dq-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .dq-empty { text-align: center; color: #64748b; padding: 30px 0; }
    @media (max-width: 600px) { .dq-row { flex-wrap: wrap; } .dq-actions { width: 100%; justify-content: flex-start; } }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.doctor_queue')
        <small class="tw-text-gray-600">{{ @format_date(\Carbon::today()) }} · @lang('clinic.doctor_queue_help')</small>
    </h1>
</section>

<section class="content">
    @if ($see_all)
        <div class="row" style="margin-bottom:10px;">
            <div class="col-sm-4">
                {!! Form::select('doctor_filter', ['all' => __('clinic.all_doctors')] + $doctors, $doctor_id, ['class' => 'form-control', 'id' => 'doctor_filter']) !!}
            </div>
        </div>
    @endif

    <div id="queue_list">
        @include('clinic.queue.partials.list')
    </div>
    <p class="help-block"><i class="fas fa-sync-alt"></i> @lang('clinic.queue_refresh_note')</p>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        function queueUrl() {
            var url = '{{ action([\App\Http\Controllers\ClinicQueueController::class, 'index']) }}?partial=1';
            if ($('#doctor_filter').length) { url += '&doctor_id=' + encodeURIComponent($('#doctor_filter').val()); }
            return url;
        }
        function refresh() {
            $.get(queueUrl(), function (html) { $('#queue_list').html(html); });
        }
        $('#doctor_filter').on('change', function () {
            refresh();
            if (window.history && history.replaceState) {
                history.replaceState(null, '', '?doctor_id=' + encodeURIComponent($(this).val()));
            }
        });
        setInterval(function () { if (! document.hidden) { refresh(); } }, 20000);

        $(document).on('submit', 'form.js-confirm', function () { return confirm($(this).data('confirm')); });
    });
</script>
@endsection
