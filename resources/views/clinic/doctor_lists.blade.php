@extends('layouts.app')
@section('title', __('clinic.dl_menu'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.dl_menu')
        <small class="tw-text-gray-600">@lang('clinic.dl_lead')</small>
    </h1>
</section>

<section class="content">
    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs" style="display:flex;flex-wrap:wrap;">
            @foreach ($lists as $key => $list)
                <li class="{{ $key == $active ? 'active' : '' }}">
                    <a href="#dl_{{ $key }}" data-toggle="tab" data-list="{{ $key }}">{{ $list['label'] }} <span class="badge">{{ $list['rows']->count() }}</span></a>
                </li>
            @endforeach
        </ul>
        <div class="tab-content">
            @foreach ($lists as $key => $list)
                <div class="tab-pane {{ $key == $active ? 'active' : '' }}" id="dl_{{ $key }}">
                    <div class="row">
                        <div class="col-md-7">
                            <table class="table table-condensed table-striped dl-table" data-list="{{ $key }}">
                                <tbody>
                                    @forelse ($list['rows'] as $row)
                                        <tr data-id="{{ $row->id }}">
                                            <td style="width:60%;">
                                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicOptionController::class, 'update'], [$row->id]) }}" class="dl-rename" style="display:flex;gap:4px;">
                                                    @csrf
                                                    <input type="text" name="value" value="{{ $row->value }}" class="form-control input-sm" maxlength="191" required aria-label="{{ $list['label'] }}">
                                                    <button type="submit" class="btn btn-xs btn-default" title="{{ __('messages.save') }}"><i class="fas fa-check"></i></button>
                                                </form>
                                            </td>
                                            <td class="text-right" style="white-space:nowrap;">
                                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicOptionController::class, 'move'], [$row->id]) }}" style="display:inline;">
                                                    @csrf
                                                    <button type="submit" name="dir" value="up" class="btn btn-xs btn-default" title="{{ __('clinic.dl_move_up') }}" {{ $loop->first ? 'disabled' : '' }}><i class="fas fa-arrow-up"></i></button>
                                                    <button type="submit" name="dir" value="down" class="btn btn-xs btn-default" title="{{ __('clinic.dl_move_down') }}" {{ $loop->last ? 'disabled' : '' }}><i class="fas fa-arrow-down"></i></button>
                                                </form>
                                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicOptionController::class, 'destroy'], [$row->id]) }}" style="display:inline;" class="dl-delete">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-danger" title="{{ __('messages.delete') }}"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td class="text-muted">@lang('clinic.dl_empty')</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-5">
                            <form method="POST" action="{{ action([\App\Http\Controllers\ClinicOptionController::class, 'store']) }}">
                                @csrf
                                <input type="hidden" name="list" value="{{ $key }}">
                                <div class="form-group">
                                    <label>@lang('clinic.dl_add_entries')</label>
                                    <textarea name="values" rows="5" class="form-control" placeholder="{{ __('clinic.dl_add_placeholder') }}" required></textarea>
                                    <p class="help-block">@lang('clinic.dl_add_help')</p>
                                </div>
                                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-sm tw-text-white"><i class="fas fa-plus"></i> @lang('messages.add')</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(document).on('submit', 'form.dl-delete', function (e) {
        var form = this;
        if (form.getAttribute('data-ok') === '1') { return; }
        e.preventDefault();
        swal({ title: LANG.sure, icon: 'warning', buttons: true, dangerMode: true }).then(function (ok) {
            if (ok) { form.setAttribute('data-ok', '1'); form.submit(); }
        });
    });
</script>
@endsection
