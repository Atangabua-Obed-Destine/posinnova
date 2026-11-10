@extends('layouts.app')
@section('title', __('clinic.dispensary'))

@section('css')
<style>
    .dp-row { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 8px; }
    .dp-row.is-partial { border-color: #38bdf8; background: #f0f9ff; }
    .dp-icon { font-size: 22px; color: #16a34a; min-width: 40px; text-align: center; }
    .dp-main { flex: 1; min-width: 0; }
    .dp-name { font-weight: 700; font-size: 15px; color: #0f172a; }
    .dp-meta { color: #64748b; font-size: 12px; }
    .dp-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .dp-empty { text-align: center; color: #64748b; padding: 30px 0; }
    @media (max-width: 600px) { .dp-row { flex-wrap: wrap; } .dp-actions { width: 100%; justify-content: flex-start; } }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.dispensary')
        <small class="tw-text-gray-600">@lang('clinic.dispensary_help')</small>
    </h1>
</section>

<section class="content">
    <div class="btn-group" style="margin-bottom:10px;">
        <a href="?show=waiting" class="btn btn-sm {{ $show == 'waiting' ? 'btn-primary' : 'btn-default' }}">@lang('clinic.rx_waiting_tab')</a>
        <a href="?show=done" class="btn btn-sm {{ $show == 'done' ? 'btn-primary' : 'btn-default' }}">@lang('clinic.rx_done_tab')</a>
    </div>

    <div id="dispensary_list">
        @include('clinic.dispensary.partials.list')
    </div>
    @if ($show == 'waiting')
        <p class="help-block"><i class="fas fa-sync-alt"></i> @lang('clinic.dispensary_refresh_note')</p>
    @endif
</section>
@endsection

@section('javascript')
@if ($show == 'waiting')
<script>
    $(function () {
        var url = '{{ action([\App\Http\Controllers\ClinicDispensaryController::class, 'index']) }}?partial=1&show=waiting';
        setInterval(function () {
            if (! document.hidden) { $.get(url, function (html) { $('#dispensary_list').html(html); }); }
        }, 30000);
    });
</script>
@endif
@endsection
