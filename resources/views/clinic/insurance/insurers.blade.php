@extends('layouts.app')
@section('title', __('clinic.ins_insurers'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.ins_insurers')
        <small class="tw-text-gray-600">@lang('clinic.ins_insurers_sub')</small>
    </h1>
</section>

<section class="content">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start;">
        @include('clinic.insurance.partials.nav', ['active' => 'insurers'])
        <a href="{{ action([\App\Http\Controllers\ClinicInsurerController::class, 'create']) }}" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" style="margin-left:auto;">
            <i class="fas fa-plus"></i> @lang('clinic.ins_add_insurer')
        </a>
    </div>

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>@lang('clinic.ins_insurer')</th>
                        <th>@lang('clinic.ins_default_pct')</th>
                        <th>@lang('clinic.ins_patients')</th>
                        <th class="text-right">@lang('clinic.ins_unclaimed')</th>
                        <th class="text-right">@lang('clinic.ins_claimed_unpaid')</th>
                        <th class="text-right">@lang('clinic.ins_owes_total')</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($insurers as $i)
                        @php $b = $balances[$i->id] ?? ['unclaimed' => 0, 'draft' => 0, 'claimed' => 0, 'total' => 0]; @endphp
                        <tr class="{{ $i->is_active ? '' : 'text-muted' }}">
                            <td><strong>{{ $i->name }}</strong>
                                @if (! $i->is_active) <span class="label label-default">@lang('clinic.ins_inactive')</span>@endif
                                @if ($i->phone || $i->email)<br><small class="text-muted">{{ trim($i->phone.' '.$i->email) }}</small>@endif
                            </td>
                            <td>{{ is_null($i->default_pct) ? '-' : \App\Utils\ClinicInsurance::pct($i->default_pct).'%' }}</td>
                            <td>{{ $patients[$i->id] ?? 0 }}</td>
                            <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $b['unclaimed'] + $b['draft'] }}</span></td>
                            <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $b['claimed'] }}</span></td>
                            <td class="text-right"><strong><span class="display_currency" data-currency_symbol="true">{{ $b['total'] }}</span></strong></td>
                            <td class="text-right" style="white-space:nowrap;">
                                <a href="{{ action([\App\Http\Controllers\ClinicInsurerController::class, 'edit'], [$i->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline"><i class="fas fa-edit"></i> @lang('messages.edit')</a>
                                <a href="{{ action([\App\Http\Controllers\ClinicClaimController::class, 'create'], ['insurer_id' => $i->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="fas fa-file-medical"></i> @lang('clinic.ins_new_claim')</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">@lang('clinic.ins_no_insurers')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent
    <p class="help-block"><i class="fas fa-info-circle"></i> @lang('clinic.ins_insurers_help')</p>
</section>
@endsection
