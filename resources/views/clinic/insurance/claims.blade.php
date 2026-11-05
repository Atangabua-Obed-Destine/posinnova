@extends('layouts.app')
@section('title', __('clinic.ins_claims'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.ins_claims')
        <small class="tw-text-gray-600">@lang('clinic.ins_claims_sub')</small>
    </h1>
</section>

<section class="content">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start;">
        @include('clinic.insurance.partials.nav', ['active' => 'claims'])
        @if ($insurers->isNotEmpty())
            <a href="{{ action([\App\Http\Controllers\ClinicClaimController::class, 'create']) }}" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" style="margin-left:auto;">
                <i class="fas fa-plus"></i> @lang('clinic.ins_new_claim')
            </a>
        @endif
    </div>

    @if ($insurers->isEmpty())
        <div class="callout callout-info">
            <p><i class="fas fa-info-circle"></i> @lang('clinic.ins_no_insurers_yet')
                <a href="{{ action([\App\Http\Controllers\ClinicInsurerController::class, 'create']) }}">@lang('clinic.ins_add_insurer')</a></p>
        </div>
    @else
        {{-- what each insurer owes --}}
        <div class="row">
            @foreach ($insurers as $i)
                @php $b = $balances[$i->id] ?? null; @endphp
                @if ($b && $b['total'] > 0)
                    <div class="col-sm-6 col-md-4">
                        <div class="tw-rounded-xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-3">
                            <div style="display:flex; justify-content:space-between; gap:8px;">
                                <strong>{{ $i->name }}</strong>
                                <strong style="color:#1d4ed8;"><span class="display_currency" data-currency_symbol="true">{{ $b['total'] }}</span></strong>
                            </div>
                            <div class="text-muted" style="font-size:12px; margin-top:6px;">
                                @lang('clinic.ins_unclaimed'): <span class="display_currency" data-currency_symbol="true">{{ $b['unclaimed'] + $b['draft'] }}</span>
                                · @lang('clinic.ins_claimed_unpaid'): <span class="display_currency" data-currency_symbol="true">{{ $b['claimed'] }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        @component('components.widget', ['class' => 'box-primary'])
            <form method="GET" class="form-inline" style="margin-bottom:10px;">
                <select name="insurer_id" class="form-control input-sm" onchange="this.form.submit()">
                    <option value="">@lang('clinic.ins_all_insurers')</option>
                    @foreach ($insurers as $i)
                        <option value="{{ $i->id }}" {{ request('insurer_id') == $i->id ? 'selected' : '' }}>{{ $i->name }}</option>
                    @endforeach
                </select>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>@lang('clinic.ins_claim')</th>
                            <th>@lang('clinic.ins_insurer')</th>
                            <th>@lang('clinic.ins_period')</th>
                            <th>@lang('clinic.ins_items')</th>
                            <th class="text-right">@lang('clinic.ins_claimed')</th>
                            <th class="text-right">@lang('lang_v1.paid')</th>
                            <th class="text-right">@lang('lang_v1.due')</th>
                            <th>@lang('sale.status')</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($claims as $c)
                            @php $s = $c->summary; @endphp
                            <tr>
                                <td><strong>{{ $c->ref_no }}</strong></td>
                                <td>{{ optional($c->insurer)->name }}</td>
                                <td>{{ @format_date($c->date_from) }} – {{ @format_date($c->date_to) }}</td>
                                <td>{{ $s['count'] }}</td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $s['claimed'] }}</span></td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $s['paid'] }}</span></td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $s['due'] }}</span></td>
                                <td>@include('clinic.insurance.partials.claim_state', ['state' => $s['state']])</td>
                                <td class="text-right"><a href="{{ action([\App\Http\Controllers\ClinicClaimController::class, 'show'], [$c->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline"><i class="fas fa-eye"></i> @lang('messages.view')</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">@lang('clinic.ins_no_claims')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $claims->links() }}
        @endcomponent
        <p class="help-block"><i class="fas fa-info-circle"></i> @lang('clinic.ins_claims_help')</p>
    @endif
</section>
@endsection
