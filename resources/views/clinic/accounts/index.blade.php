@extends('layouts.app')
@section('title', __('clinic.patient_accounts'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.patient_accounts')
        <small class="tw-text-gray-600">@lang('clinic.patient_accounts_help')</small>
    </h1>
</section>

<section class="content">
    <form method="GET" class="row" style="margin-bottom:12px;">
        <div class="col-sm-6">
            <div class="input-group">
                <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('clinic.acc_search') }}" autofocus>
                <span class="input-group-btn"><button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button></span>
            </div>
        </div>
        @if ($search !== '')
            <div class="col-sm-3"><a href="?" class="btn btn-default">@lang('clinic.acc_show_owing')</a></div>
        @endif
    </form>

    @component('components.widget', ['class' => 'box-primary', 'title' => $search !== '' ? __('clinic.acc_search_results') : __('clinic.acc_owing')])
        @if ($total_outstanding !== null)
            <p style="font-size:16px;">@lang('clinic.acc_total_outstanding'):
                <strong class="text-danger"><span class="display_currency" data-currency_symbol="true">{{ $total_outstanding }}</span></strong>
                · {{ trans_choice('clinic.acc_patients_count', $patients->count(), ['n' => $patients->count()]) }}</p>
        @endif
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>@lang('lang_v1.patient')</th>
                        <th>@lang('lang_v1.file_number')</th>
                        <th>@lang('contact.mobile')</th>
                        <th>@lang('clinic.acc_owing_since')</th>
                        <th class="text-right">@lang('clinic.acc_balance')</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($patients as $p)
                        <tr>
                            <td><strong>{{ $p->name }}</strong></td>
                            <td>{{ $p->file_number ?: '-' }}</td>
                            <td>{{ $p->mobile ?: '-' }}</td>
                            <td>{{ $p->oldest_due ? @format_date($p->oldest_due) : '-' }}</td>
                            <td class="text-right {{ $p->balance > 0 ? 'text-danger' : 'text-muted' }}"><strong><span class="display_currency" data-currency_symbol="true">{{ $p->balance }}</span></strong></td>
                            <td class="text-right">
                                <a href="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$p->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white">
                                    <i class="fas fa-wallet"></i> @lang('clinic.acc_open')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ $search !== '' ? __('lang_v1.no_records_found') : __('clinic.acc_nobody_owes') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent
</section>
@endsection
