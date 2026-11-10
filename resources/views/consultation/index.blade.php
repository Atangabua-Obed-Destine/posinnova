@extends('layouts.app')
@section('title', __('lang_v1.consultations'))

{{-- PosInnova: list of consultations (own, or everyone's with consultation.view_all) --}}
@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.consultations')
        <small class="tw-text-gray-600">{{ auth()->user()->can('consultation.view_all') ? __('clinic.cons_list_all') : __('clinic.cons_list_mine') }}</small>
    </h1>
</section>

<section class="content">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;max-width:620px;">
        <input type="search" name="q" value="{{ $search }}" class="form-control" style="flex:1;min-width:200px;"
            placeholder="{{ __('clinic.cons_search') }}" aria-label="{{ __('clinic.cons_search') }}">
        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white"><i class="fas fa-search"></i> @lang('lang_v1.search')</button>
        @if ($search !== '')
            <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('clinic.cons_clear')</a>
        @endif
    </form>

    @component('components.widget', ['class' => 'box-primary'])
        @if ($consultations->isEmpty())
            <p class="text-muted" style="margin:0;">{{ $search !== '' ? __('clinic.cons_none_found') : __('clinic.cons_none') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-striped" id="consultations_table">
                    <thead><tr>
                        <th>@lang('lang_v1.date')</th><th>@lang('clinic.rep_patient')</th><th>@lang('clinic.rep_file_no')</th>
                        <th>@lang('clinic.rep_diagnosis')</th><th>@lang('clinic.rep_doctor')</th><th>@lang('sale.status')</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($consultations as $c)
                            <tr>
                                <td style="white-space:nowrap;">{{ @format_datetime($c->created_at) }}</td>
                                <td><a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'show'], [$c->id]) }}"><strong>{{ $c->patient_name }}</strong></a></td>
                                <td>{{ $c->file_number }}</td>
                                <td>{{ \Illuminate\Support\Str::limit((string) $c->diagnosis, 70) ?: '-' }}</td>
                                <td>{{ trim(optional($c->consultedBy)->user_full_name) }}</td>
                                <td><span class="label {{ $c->status == 'final' ? 'label-success' : 'label-warning' }}">{{ __('lang_v1.consultation_status_' . $c->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-center">{{ $consultations->links() }}</div>
        @endif
    @endcomponent
</section>
@endsection
