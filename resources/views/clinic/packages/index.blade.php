@extends('layouts.app')
@section('title', __('clinic.packages'))

@php $fmt = function ($n) { return app(\App\Utils\Util::class)->num_f((float) $n); }; @endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.packages')
        <small class="tw-text-gray-600">@lang('clinic.packages_help')</small>
    </h1>
</section>

<section class="content">
    @component('components.widget', ['class' => 'box-solid'])
        @slot('tool')
            <div class="box-tools">
                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                    href="{{ action([\App\Http\Controllers\ClinicPackageController::class, 'create']) }}">
                    <i class="fa fa-plus"></i> @lang('clinic.add_package')
                </a>
            </div>
        @endslot

        @if ($packages->isEmpty())
            <p class="text-muted" style="padding:20px 0;">@lang('clinic.no_packages')</p>
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="clinic_packages_table">
                    <thead>
                        <tr>
                            <th>@lang('clinic.package_name')</th>
                            <th>@lang('clinic.package_covers')</th>
                            <th class="text-right">@lang('clinic.package_price')</th>
                            <th>@lang('clinic.package_billing')</th>
                            <th class="text-right">@lang('clinic.times_used')</th>
                            <th>@lang('lang_v1.status')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($packages as $p)
                            <tr>
                                <td><strong>{{ $p->name }}</strong></td>
                                <td style="white-space:pre-line; max-width:360px;">{{ $p->description ?: '-' }}</td>
                                <td class="text-right">{{ $fmt($p->price) }}</td>
                                <td>
                                    @if ($p->billing == 'monthly')
                                        @lang('clinic.billing_monthly')
                                        <br><small class="text-muted">{{ $p->visits_per_month ? __('clinic.visits_per_month_n', ['n' => $p->visits_per_month]) : __('clinic.visits_unlimited') }}</small>
                                    @else
                                        @lang('clinic.billing_per_visit')
                                    @endif
                                </td>
                                <td class="text-right">{{ $usage[$p->id] ?? 0 }}</td>
                                <td>
                                    @if ($p->is_active)
                                        <span class="label label-success">@lang('clinic.active')</span>
                                    @else
                                        <span class="label label-default">@lang('clinic.inactive')</span>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">
                                    <a href="{{ action([\App\Http\Controllers\ClinicPackageController::class, 'edit'], [$p->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="fa fa-edit"></i> @lang('messages.edit')</a>
                                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicPackageController::class, 'destroy'], [$p->id]) }}" style="display:inline;" class="js-confirm" data-confirm="{{ __('clinic.confirm_delete_package') }}">
                                        @csrf
                                        <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error"><i class="fa fa-trash"></i> @lang('messages.delete')</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="help-block">@lang('clinic.packages_footer_help')</p>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>$(document).on('submit', 'form.js-confirm', function () { return confirm($(this).data('confirm')); });</script>
@endsection
