@extends('layouts.app')
@section('title', __('inventory_count.inventory_count'))

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('inventory_count.inventory_count')
        <small class="tw-block tw-text-sm tw-font-normal tw-text-gray-500 tw-mt-1">
            @lang('inventory_count.subtitle')
        </small>
    </h1>
</section>

<section class="content">
    <div class="alert alert-info">
        <strong>@lang('inventory_count.use_this_for')</strong>
        @lang('inventory_count.use_this_for_text')
        <br>
        <strong>@lang('inventory_count.not_for')</strong>
        @lang('inventory_count.not_for_text')
        <a href="{{ action([\App\Http\Controllers\StockAdjustmentController::class, 'create']) }}">@lang('stock_adjustment.add')</a>.
    </div>

    @if(session('status'))
        @php $st = session('status'); @endphp
        <div class="alert alert-{{ ($st['success'] ?? 0) ? 'success' : 'danger' }}">
            {{ $st['msg'] ?? '' }}
        </div>
    @endif

    {{-- ============ LOCATION PICKER ============ --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => __('business.business_location')])
        <form method="GET" action="{{ url('inventory-count') }}" class="form-inline">
            <div class="form-group">
                <label for="ic_location_id" class="tw-mr-2">@lang('business.business_location'):</label>
                {!! Form::select('location_id', $business_locations, $location_id, [
                    'class' => 'form-control select2', 'id' => 'ic_location_id',
                    'placeholder' => __('messages.please_select'),
                    'style' => 'min-width:260px;',
                    'onchange' => 'this.form.submit()'
                ]) !!}
            </div>
        </form>
        @if(! $location_id)
            <p class="text-muted tw-mt-3 tw-mb-0">@lang('inventory_count.select_location_to_start')</p>
        @endif
    @endcomponent

    @if($location_id)
        {{-- ============ START NEW (with scope) ============ --}}
        @if($can_create)
        @component('components.widget', ['class' => 'box-success', 'title' => __('inventory_count.start_new_count')])
            <form method="POST" action="{{ url('inventory-count/create') }}" id="ic_start_form">
                @csrf
                <input type="hidden" name="location_id" value="{{ $location_id }}">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>@lang('inventory_count.scope')</label>
                        <select name="scope_type" id="ic_scope_type" class="form-control">
                            <option value="all">@lang('inventory_count.scope_all')</option>
                            <option value="category">@lang('inventory_count.scope_category')</option>
                            <option value="brand">@lang('inventory_count.scope_brand')</option>
                        </select>
                    </div>
                    <div class="col-md-7 form-group" id="ic_scope_category_wrap" style="display:none;">
                        <label>@lang('inventory_count.choose_categories')</label>
                        {!! Form::select('scope_ids[]', $categories, null, [
                            'class' => 'form-control select2',
                            'multiple' => true,
                            'id' => 'ic_scope_categories',
                            'style' => 'width:100%;',
                        ]) !!}
                    </div>
                    <div class="col-md-7 form-group" id="ic_scope_brand_wrap" style="display:none;">
                        <label>@lang('inventory_count.choose_brands')</label>
                        {!! Form::select('scope_ids[]', $brands, null, [
                            'class' => 'form-control select2',
                            'multiple' => true,
                            'id' => 'ic_scope_brands',
                            'style' => 'width:100%;',
                        ]) !!}
                    </div>
                    <div class="col-md-2 form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary form-control"
                            onclick="return confirm(@json(__('inventory_count.start_new_count') . '?'))">
                            <i class="fa fa-plus"></i> @lang('inventory_count.start_new_count')
                        </button>
                    </div>
                </div>
                <p class="text-muted tw-mt-2 tw-mb-0"><small>@lang('inventory_count.scope_help')</small></p>
            </form>
        @endcomponent
        @endif

        {{-- ============ ACTIONS ============ --}}
        <div class="tw-mb-3 tw-flex tw-flex-wrap tw-gap-2">
            <a href="{{ url('inventory-count/blank-pdf?location_id='.$location_id) }}"
               class="btn btn-default" target="_blank">
                <i class="fa fa-file-pdf-o"></i> @lang('inventory_count.download_blank_pdf')
            </a>

            <a href="{{ url('inventory-count/blank-pdf?location_id='.$location_id.'&blind=1') }}"
               class="btn btn-default" target="_blank">
                <i class="fa fa-eye-slash"></i> @lang('inventory_count.blind_count_pdf')
            </a>
        </div>

        {{-- ============ DRAFTS ============ --}}
        @component('components.widget', ['class' => 'box-warning', 'title' => __('inventory_count.draft_sessions')])
            @if($drafts->isEmpty())
                <p class="text-muted tw-mb-0">@lang('inventory_count.no_drafts')</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>@lang('inventory_count.reference')</th>
                                <th>@lang('inventory_count.scope')</th>
                                <th>@lang('inventory_count.started')</th>
                                <th>@lang('inventory_count.started_by')</th>
                                <th>@lang('inventory_count.progress')</th>
                                <th class="text-right">@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($drafts as $d)
                                <tr>
                                    <td><strong>{{ $d->reference_no }}</strong></td>
                                    <td>
                                        @if($d->scope_type === 'all')
                                            <span class="label label-default">@lang('inventory_count.scope_all')</span>
                                        @else
                                            <span class="label label-info">@lang('inventory_count.scope_' . $d->scope_type)</span>
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($d->scope_label, 40) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $d->started_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ optional($d->startedBy)->first_name }} {{ optional($d->startedBy)->surname }}</td>
                                    <td>
                                        {{ $d->lines_counted }} / {{ $d->lines_total }}
                                        @if($d->lines_total > 0)
                                            <div class="progress" style="height:6px; margin-top:4px;">
                                                <div class="progress-bar progress-bar-warning" role="progressbar"
                                                    style="width: {{ round(($d->lines_counted / $d->lines_total) * 100) }}%;"></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ url('inventory-count/'.$d->id) }}" class="btn btn-sm btn-primary">
                                            <i class="fa fa-edit"></i> @lang('inventory_count.resume')
                                        </a>
                                        @if($can_cancel)
                                            <button type="button" class="btn btn-sm btn-danger btn-cancel-session"
                                                data-session-id="{{ $d->id }}">
                                                <i class="fa fa-times"></i> @lang('messages.cancel')
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endcomponent

        {{-- ============ PENDING APPROVAL ============ --}}
        @component('components.widget', ['class' => 'box-primary', 'title' => __('inventory_count.pending_approval_sessions')])
            @if($pending->isEmpty())
                <p class="text-muted tw-mb-0">@lang('inventory_count.no_pending_approval')</p>
            @else
                <div class="table-responsive">
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                                <th>@lang('inventory_count.reference_no')</th>
                                <th>@lang('inventory_count.submitted_at')</th>
                                <th>@lang('inventory_count.submitted_by')</th>
                                <th>@lang('inventory_count.scope')</th>
                                <th>@lang('inventory_count.progress')</th>
                                <th>@lang('inventory_count.reason')</th>
                                <th class="text-right">@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pending as $p)
                                <tr>
                                    <td><strong>{{ $p->reference_no }}</strong></td>
                                    <td>{{ optional($p->posted_at)->format('Y-m-d H:i') }}</td>
                                    <td>{{ optional($p->postedBy)->first_name }} {{ optional($p->postedBy)->surname }}</td>
                                    <td>
                                        @if($p->scope_type && $p->scope_type !== 'all')
                                            <span class="label label-info" title="{{ $p->scope_label }}">
                                                @lang('inventory_count.scope_'.$p->scope_type)
                                            </span>
                                        @else
                                            <span class="label label-default">@lang('inventory_count.scope_all')</span>
                                        @endif
                                    </td>
                                    <td>{{ $p->lines_counted }} / {{ $p->lines_total }}</td>
                                    <td>{{ $reasons[$p->reason] ?? $p->reason }}</td>
                                    <td class="text-right">
                                        <a href="{{ url('inventory-count/'.$p->id.'/variance-pdf') }}"
                                           class="btn btn-sm btn-info" target="_blank" title="@lang('inventory_count.variance_report')">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </a>
                                        <a href="{{ url('inventory-count/'.$p->id) }}" class="btn btn-sm btn-primary">
                                            <i class="fa fa-eye"></i>
                                            @if($can_approve)
                                                @lang('inventory_count.review_and_approve')
                                            @else
                                                @lang('inventory_count.view')
                                            @endif
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endcomponent

        {{-- ============ RECOUNT QUEUE LINK ============ --}}
        @component('components.widget', ['class' => 'box-warning', 'title' => __('inventory_count.recount_queue')])
            <p class="tw-mb-2 text-muted">@lang('inventory_count.recount_queue_subtitle')</p>
            @if(($recount_queue_count ?? 0) > 0)
                <a href="{{ url('inventory-count/recount-queue?location_id='.$location_id) }}"
                   class="btn btn-warning">
                    <i class="fa fa-flag"></i>
                    @lang('inventory_count.view_recount_queue')
                    <span class="badge">{{ $recount_queue_count }}</span>
                </a>
            @else
                <p class="tw-mb-0 text-success"><i class="fa fa-check"></i> @lang('inventory_count.no_recount_items')</p>
            @endif
        @endcomponent

        {{-- ============ RECENT ============ --}}
        @component('components.widget', ['class' => 'box-default', 'title' => __('inventory_count.recent_sessions')])
            @if($recent->isEmpty())
                <p class="text-muted tw-mb-0">@lang('inventory_count.no_recent')</p>
            @else
                <div class="table-responsive">
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                                <th>@lang('inventory_count.reference')</th>
                                <th>@lang('inventory_count.status')</th>
                                <th>@lang('inventory_count.posted_at')</th>
                                <th>@lang('inventory_count.posted_by')</th>
                                <th class="text-right" title="@lang('inventory_count.col_shortage_help')">
                                    @lang('inventory_count.shortages') <small class="text-muted">(@lang('inventory_count.units_value'))</small>
                                </th>
                                <th class="text-right" title="@lang('inventory_count.col_surplus_help')">
                                    @lang('inventory_count.surpluses') <small class="text-muted">(@lang('inventory_count.units_value'))</small>
                                </th>
                                <th class="text-right">@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent as $r)
                                <tr>
                                    <td>{{ $r->reference_no }}</td>
                                    <td>
                                        @if($r->status === 'posted')
                                            <span class="label label-success">@lang('inventory_count.status_posted')</span>
                                        @else
                                            <span class="label label-default">@lang('inventory_count.status_cancelled')</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($r->posted_at ?? $r->cancelled_at)->format('Y-m-d H:i') }}</td>
                                    <td>{{ optional($r->postedBy)->first_name }} {{ optional($r->postedBy)->surname }}</td>
                                    <td class="text-right text-danger">
                                        @if($r->status === 'posted')
                                            @if(($r->shortage_lines ?? 0) > 0)
                                                <strong>−{{ rtrim(rtrim(number_format($r->shortage_units, 4), '0'), '.') }}</strong>
                                                <small class="text-muted">/ {{ $r->shortage_lines }} @lang('inventory_count.lines_abbrev')</small>
                                                <br><small>{{ number_format($r->shortage_value, 2) }}</small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        @else — @endif
                                    </td>
                                    <td class="text-right text-success">
                                        @if($r->status === 'posted')
                                            @if(($r->surplus_lines ?? 0) > 0)
                                                <strong>+{{ rtrim(rtrim(number_format($r->surplus_units, 4), '0'), '.') }}</strong>
                                                <small class="text-muted">/ {{ $r->surplus_lines }} @lang('inventory_count.lines_abbrev')</small>
                                                <br><small>{{ number_format($r->surplus_value, 2) }}</small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        @else — @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ url('inventory-count/'.$r->id) }}" class="btn btn-xs btn-default">
                                            <i class="fa fa-eye"></i> @lang('messages.view')
                                        </a>
                                        @if($r->status === 'posted')
                                            <a href="{{ url('inventory-count/'.$r->id.'/variance-pdf') }}"
                                               class="btn btn-xs btn-info" target="_blank"
                                               title="@lang('inventory_count.download_variance_report')">
                                                <i class="fa fa-file-pdf-o"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endcomponent
    @endif
</section>

@endsection

@section('javascript')
<script>
$(function () {
    function refreshScopeUi() {
        var v = $('#ic_scope_type').val();
        $('#ic_scope_category_wrap').toggle(v === 'category');
        $('#ic_scope_brand_wrap').toggle(v === 'brand');
        // disable hidden selects so their values are not submitted
        $('#ic_scope_categories').prop('disabled', v !== 'category');
        $('#ic_scope_brands').prop('disabled', v !== 'brand');
    }
    $('#ic_scope_type').on('change', refreshScopeUi);
    refreshScopeUi();

    $('.btn-cancel-session').on('click', function () {
        if (! confirm(@json(__('inventory_count.confirm_cancel_session')))) return;
        var sid = $(this).data('session-id');
        $.ajax({
            url: '{{ url('inventory-count') }}/' + sid + '/cancel',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json',
            success: function (resp) {
                if (resp.success) {
                    window.location.href = resp.redirect;
                } else {
                    toastr.error(resp.msg || 'Error');
                }
            },
            error: function () { toastr.error('Network error'); }
        });
    });
});
</script>
@endsection
