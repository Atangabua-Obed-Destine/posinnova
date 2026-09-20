@extends('layouts.app')
@section('title', __('lang_v1.wards'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.wards')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.ward_bed_overview')</small>
        </h1>
    </section>

    <section class="content">
        @php
            $allBeds = $wards->pluck('beds')->flatten();
            $tBeds = $allBeds->count();
            $tOcc = $allBeds->where('status', 'occupied')->count();
            $tAvail = $allBeds->where('status', 'available')->count();
            $tMaint = $allBeds->where('status', 'maintenance')->count();
            $occPct = $tBeds ? round($tOcc / $tBeds * 100) : 0;
        @endphp
        <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-5 tw-gap-3 tw-mb-4">
            <div class="tw-bg-white tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-3"><p class="tw-text-xs tw-text-gray-500">@lang('lang_v1.occupancy')</p><p class="tw-text-2xl tw-font-extrabold {{ $occPct >= 90 ? 'tw-text-red-600' : 'tw-text-indigo-600' }}">{{ $occPct }}%</p></div>
            <div class="tw-bg-white tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-3"><p class="tw-text-xs tw-text-gray-500">@lang('lang_v1.beds')</p><p class="tw-text-2xl tw-font-extrabold tw-text-gray-800">{{ $tBeds }}</p></div>
            <div class="tw-bg-white tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-3"><p class="tw-text-xs tw-text-gray-500">@lang('lang_v1.occupied')</p><p class="tw-text-2xl tw-font-extrabold tw-text-red-600">{{ $tOcc }}</p></div>
            <div class="tw-bg-white tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-3"><p class="tw-text-xs tw-text-gray-500">@lang('lang_v1.available')</p><p class="tw-text-2xl tw-font-extrabold tw-text-green-600">{{ $tAvail }}</p></div>
            <div class="tw-bg-white tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-3"><p class="tw-text-xs tw-text-gray-500">@lang('lang_v1.maintenance')</p><p class="tw-text-2xl tw-font-extrabold tw-text-gray-500">{{ $tMaint }}</p></div>
        </div>
        @can('ward.manage')
            <div class="tw-mb-4 text-right">
                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full"
                    href="{{ action([\App\Http\Controllers\WardController::class, 'create']) }}">
                    <i class="fa fa-plus"></i> @lang('lang_v1.add_ward')
                </a>
            </div>
        @endcan

        @forelse($wards as $ward)
            @php
                $total = $ward->beds->count();
                $occupied = $ward->beds->where('status', 'occupied')->count();
                $available = $ward->beds->where('status', 'available')->count();
            @endphp
            <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
                <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2 tw-mb-3">
                    <div>
                        <span class="tw-text-lg tw-font-semibold tw-text-gray-900">{{ $ward->name }}</span>
                        @if(!$ward->is_active) <span class="label label-danger">@lang('lang_v1.inactive')</span> @endif
                        @if($ward->location) <small class="tw-text-gray-500">· {{ $ward->location->name }}</small> @endif
                    </div>
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <span class="tw-text-sm"><span class="label label-success">{{ $available }}</span> @lang('lang_v1.available')</span>
                        <span class="tw-text-sm"><span class="label label-danger">{{ $occupied }}</span> @lang('lang_v1.occupied')</span>
                        <span class="tw-text-sm tw-text-gray-500">{{ $total }} @lang('lang_v1.beds')</span>
                        @can('ward.manage')
                            <a href="{{ action([\App\Http\Controllers\WardController::class, 'edit'], [$ward->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="fa fa-edit"></i></a>
                            <button data-href="{{ action([\App\Http\Controllers\WardController::class, 'destroy'], [$ward->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_ward_button"><i class="fa fa-trash"></i></button>
                        @endcan
                    </div>
                </div>

                @if($total === 0)
                    <p class="text-muted">@lang('lang_v1.no_beds_configured')</p>
                @else
                    <div class="tw-mb-3 tw-h-1.5 tw-rounded-full tw-bg-gray-100 tw-overflow-hidden"><div class="tw-h-full {{ ($total && $occupied/$total >= 0.9) ? 'tw-bg-red-500' : 'tw-bg-indigo-500' }}" style="width: {{ $total ? round($occupied/$total*100) : 0 }}%"></div></div>
                    <div class="tw-flex tw-flex-wrap tw-gap-2">
                        @foreach($ward->beds->sortBy('bed_number') as $bed)
                            @php
                                $occupant = $occupants->get($bed->id);
                                $cls = $bed->status === 'occupied' ? 'tw-bg-red-100 tw-text-red-700 tw-ring-red-300'
                                    : ($bed->status === 'maintenance' ? 'tw-bg-gray-100 tw-text-gray-500 tw-ring-gray-300' : 'tw-bg-green-100 tw-text-green-700 tw-ring-green-300');
                                $title = $occupant ? optional($occupant->patient)->name : __('lang_v1.bed_status_' . $bed->status);
                                $lowDeposit = $occupant && $occupant->deposit_amount > 0 && $occupant->days_admitted > 3 && $occupant->deposit_amount < ($occupant->days_admitted * 100);
                                $tile = 'tw-inline-flex tw-flex-col tw-items-center tw-justify-center tw-min-w-[64px] tw-px-2 tw-py-2 tw-rounded-lg tw-ring-1 '.$cls;
                            @endphp
                            @if($occupant && auth()->user()->can('admission.view'))
                            <a href="{{ action([\App\Http\Controllers\AdmissionController::class, 'show'], [$occupant->id]) }}" class="{{ $tile }} tw-relative hover:tw-brightness-95" title="{{ $title }}">
                                @if($lowDeposit)<span class="tw-absolute tw-top-0.5 tw-right-1 tw-text-amber-500" title="@lang('lang_v1.low_deposit')"><i class="fas fa-circle-exclamation"></i></span>@endif
                                <i class="fas fa-bed"></i>
                                <span class="tw-text-xs tw-font-semibold tw-mt-1">{{ $bed->bed_number }}</span>
                                <span class="tw-text-[10px] tw-truncate tw-max-w-[72px]">{{ \Illuminate\Support\Str::limit(optional($occupant->patient)->name, 10) }}</span>
                                <span class="tw-text-[9px] tw-opacity-70">{{ $occupant->days_admitted }}@lang('lang_v1.day_short')</span>
                            </a>
                            @else
                            <div class="{{ $tile }}" title="{{ $title }}">
                                <i class="fas fa-bed"></i>
                                <span class="tw-text-xs tw-font-semibold tw-mt-1">{{ $bed->bed_number }}</span>
                                @if($occupant)<span class="tw-text-[10px] tw-truncate tw-max-w-[72px]">{{ \Illuminate\Support\Str::limit(optional($occupant->patient)->name, 10) }}</span>@endif
                            </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-8 tw-text-center tw-text-gray-500">
                @lang('lang_v1.no_wards_create_first')
            </div>
        @endforelse
    </section>
@endsection

@section('javascript')
<script>
$(document).on('click', 'button.delete_ward_button', function () {
    var href = $(this).data('href');
    swal({ title: LANG.sure, icon: 'warning', buttons: true, dangerMode: true }).then(function (yes) {
        if (yes) {
            $.ajax({
                method: 'DELETE', url: href, dataType: 'json',
                success: function (result) {
                    if (result.success === true) { toastr.success(result.msg); setTimeout(function(){ location.reload(); }, 700); }
                    else { toastr.error(result.msg); }
                },
            });
        }
    });
});
</script>
@endsection
