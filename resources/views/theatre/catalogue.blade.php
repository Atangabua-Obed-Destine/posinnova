@extends('layouts.app')
@section('title', __('lang_v1.procedure_catalogue'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.procedure_catalogue')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.manage_procedures')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_procedures')])
            @slot('tool')
                <div class="box-tools">
                    <a class="tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal tw-dw-btn pull-right"
                        data-href="{{ action([\App\Http\Controllers\TheatreController::class, 'createProcedure']) }}"
                        data-container=".procedure_modal">
                        <i class="fa fa-plus"></i> @lang('messages.add')
                    </a>
                </div>
            @endslot

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="procedures_table">
                    <thead>
                        <tr>
                            <th>@lang('lang_v1.procedure_name')</th>
                            <th>@lang('lang_v1.category')</th>
                            <th>@lang('lang_v1.fee')</th>
                            <th>@lang('lang_v1.anaesthesia_fee')</th>
                            <th>@lang('lang_v1.status')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcomponent

        <div class="modal fade procedure_modal" tabindex="-1" role="dialog"></div>
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    var t = $('#procedures_table').DataTable({
        processing: true, serverSide: true,
        ajax: '{{ action([\App\Http\Controllers\TheatreController::class, 'catalogue']) }}',
        columns: [
            { data: 'name', name: 'name' },
            { data: 'category', name: 'category' },
            { data: 'fee', name: 'fee' },
            { data: 'anaesthesia_fee', name: 'anaesthesia_fee' },
            { data: 'is_active', name: 'is_active' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        fnDrawCallback: function () { __currency_convert_recursively($('#procedures_table')); },
    });

    $(document).on('submit', 'form#procedure_add_form', function (e) {
        e.preventDefault(); var form = $(this);
        $.ajax({ method: 'POST', url: form.attr('action'), dataType: 'json', data: form.serialize(),
            beforeSend: function () { __disable_submit_button(form.find('button[type="submit"]')); },
            success: function (r) { if (r.success) { $('div.procedure_modal').modal('hide'); toastr.success(r.msg); t.ajax.reload(); } else { toastr.error(r.msg); } } });
    });

    $(document).on('click', 'button.edit_procedure_button', function () {
        $('div.procedure_modal').load($(this).data('href'), function () {
            $(this).modal('show');
            $('form#procedure_edit_form').submit(function (e) {
                e.preventDefault(); var form = $(this);
                $.ajax({ method: 'POST', url: form.attr('action'), dataType: 'json', data: form.serialize(),
                    beforeSend: function () { __disable_submit_button(form.find('button[type="submit"]')); },
                    success: function (r) { if (r.success) { $('div.procedure_modal').modal('hide'); toastr.success(r.msg); t.ajax.reload(); } else { toastr.error(r.msg); } } });
            });
        });
    });

    $(document).on('click', 'button.delete_procedure_button', function () {
        var href = $(this).data('href');
        swal({ title: LANG.sure, icon: 'warning', buttons: true, dangerMode: true }).then(function (yes) {
            if (yes) { $.ajax({ method: 'DELETE', url: href, dataType: 'json', success: function (r) { if (r.success) { toastr.success(r.msg); t.ajax.reload(); } else { toastr.error(r.msg); } } }); }
        });
    });
});
</script>
@endsection
