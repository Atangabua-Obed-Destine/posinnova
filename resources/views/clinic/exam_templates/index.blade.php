@extends('layouts.app')
@section('title', __('clinic.ex_menu'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.ex_menu')
        <small class="tw-text-gray-600">@lang('clinic.ex_lead')</small>
    </h1>
</section>

<section class="content">
    @component('components.widget', ['class' => 'box-solid'])
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;">
            <a href="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'create']) }}" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" id="ex_new_tpl">
                <i class="fas fa-plus"></i> @lang('clinic.ex_tpl_new')</a>
            <div class="btn-group">
                <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="ex_ready_btn">
                    <i class="fas fa-magic"></i> @lang('clinic.ex_tpl_ready') <span class="caret"></span></button>
                <ul class="dropdown-menu">
                    @foreach (\App\Utils\ClinicExamTemplates::READY_MADE as $key => $label)
                        <li>
                            <form method="POST" action="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'readyMade']) }}" style="margin:0;">
                                @csrf
                                <input type="hidden" name="key" value="{{ $key }}">
                                <button type="submit" class="btn btn-link" style="width:100%;text-align:left;padding:4px 18px;color:#333;">@lang($label)</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
            <span class="text-muted" style="font-size:12.5px;"><i class="fas fa-info-circle"></i> @lang('clinic.ex_index_help')</span>
            <a href="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'guide']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="margin-left:auto;" id="ex_guide_link"><i class="fas fa-book-open"></i> @lang('clinic.ex_guide_btn')</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped" id="ex_tpl_table">
                <thead>
                    <tr>
                        <th>@lang('clinic.ex_tpl_name')</th>
                        <th>@lang('clinic.ex_tpl_specialties')</th>
                        <th>@lang('clinic.ex_tpl_content')</th>
                        <th>@lang('clinic.ex_tpl_status')</th>
                        <th class="text-right">@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $t)
                        <tr data-id="{{ $t->id }}">
                            <td><a href="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'edit'], [$t->id]) }}"><strong>{{ $t->name }}</strong></a></td>
                            <td>
                                @forelse ((array) $t->specialties as $sp)
                                    <span class="label label-default" style="display:inline-block;margin:1px;">{{ $sp }}</span>
                                @empty
                                    <span class="text-muted">@lang('clinic.ex_tpl_no_specialty')</span>
                                @endforelse
                            </td>
                            <td class="text-muted">{{ __('clinic.ex_tpl_counts', ['drawings' => $t->drawingCount(), 'fields' => $t->fieldCount()]) }}</td>
                            <td>
                                @if ($t->is_active)
                                    <span class="label label-success">@lang('clinic.ex_tpl_active')</span>
                                @else
                                    <span class="label label-default">@lang('clinic.ex_tpl_inactive')</span>
                                @endif
                            </td>
                            <td class="text-right" style="white-space:nowrap;">
                                <a href="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'edit'], [$t->id]) }}" class="btn btn-xs btn-primary"><i class="fas fa-pen"></i> @lang('messages.edit')</a>
                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'duplicate'], [$t->id]) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-default"><i class="fas fa-copy"></i> @lang('clinic.ex_tpl_duplicate')</button>
                                </form>
                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'toggle'], [$t->id]) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-default">{{ $t->is_active ? __('clinic.ex_tpl_deactivate') : __('clinic.ex_tpl_activate') }}</button>
                                </form>
                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'destroy'], [$t->id]) }}" style="display:inline;" class="ex-tpl-delete">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-danger" title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center">@lang('clinic.ex_tpl_none')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).on('submit', '.ex-tpl-delete', function (e) {
    var f = this;
    if (f.dataset.ok) { return; }
    e.preventDefault();
    swal({ title: LANG.sure, text: @json(__('clinic.ex_tpl_delete_help')), icon: 'warning', buttons: true, dangerMode: true })
        .then(function (ok) { if (ok) { f.dataset.ok = 1; f.submit(); } });
});
</script>
@endsection
