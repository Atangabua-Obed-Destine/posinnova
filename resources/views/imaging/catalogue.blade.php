@extends('layouts.app')
@section('title', __('lang_v1.imaging_catalogue'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.imaging_catalogue')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.manage_imaging')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_imaging_studies')])
            @slot('tool')
                <div class="box-tools">
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal pull-right"
                        data-href="{{ action([\App\Http\Controllers\ImagingController::class, 'createCatalogue']) }}"
                        data-container=".imaging_modal">
                        <i class="fa fa-plus"></i> @lang('messages.add')
                    </a>
                </div>
            @endslot

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="imaging_catalogue_table">
                    <thead>
                        <tr>
                            <th>@lang('lang_v1.study_name')</th>
                            <th>@lang('lang_v1.code')</th>
                            <th>@lang('lang_v1.modality')</th>
                            <th>@lang('lang_v1.fee')</th>
                            <th>@lang('lang_v1.status')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcomponent

        <div class="modal fade imaging_modal" tabindex="-1" role="dialog"></div>
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    var imaging_table = $('#imaging_catalogue_table').DataTable({
        processing: true, serverSide: true,
        ajax: '{{ action([\App\Http\Controllers\ImagingController::class, 'catalogue']) }}',
        columns: [
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'modality', name: 'modality' },
            { data: 'fee', name: 'fee' },
            { data: 'is_active', name: 'is_active' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        fnDrawCallback: function () { __currency_convert_recursively($('#imaging_catalogue_table')); },
    });

    $(document).on('submit', 'form#imaging_add_form', function (e) {
        e.preventDefault();
        var form = $(this);
        $.ajax({
            method: 'POST', url: form.attr('action'), dataType: 'json', data: form.serialize(),
            beforeSend: function () { __disable_submit_button(form.find('button[type="submit"]')); },
            success: function (result) {
                if (result.success === true) { $('div.imaging_modal').modal('hide'); toastr.success(result.msg); imaging_table.ajax.reload(); }
                else { toastr.error(result.msg); }
            },
        });
    });

    $(document).on('click', 'button.edit_imaging_button', function () {
        $('div.imaging_modal').load($(this).data('href'), function () {
            $(this).modal('show');
            $('form#imaging_edit_form').submit(function (e) {
                e.preventDefault();
                var form = $(this);
                $.ajax({
                    method: 'POST', url: form.attr('action'), dataType: 'json', data: form.serialize(),
                    beforeSend: function () { __disable_submit_button(form.find('button[type="submit"]')); },
                    success: function (result) {
                        if (result.success === true) { $('div.imaging_modal').modal('hide'); toastr.success(result.msg); imaging_table.ajax.reload(); }
                        else { toastr.error(result.msg); }
                    },
                });
            });
        });
    });

    $(document).on('click', 'button.delete_imaging_button', function () {
        var href = $(this).data('href');
        swal({ title: LANG.sure, icon: 'warning', buttons: true, dangerMode: true }).then(function (yes) {
            if (yes) {
                $.ajax({
                    method: 'DELETE', url: href, dataType: 'json',
                    success: function (result) {
                        if (result.success === true) { toastr.success(result.msg); imaging_table.ajax.reload(); }
                        else { toastr.error(result.msg); }
                    },
                });
            }
        });
    });
});
</script>
@endsection
