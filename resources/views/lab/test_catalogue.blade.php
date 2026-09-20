@extends('layouts.app')
@section('title', __('lang_v1.lab_test_catalogue'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.lab_test_catalogue')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.manage_lab_tests')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_lab_tests')])
            @slot('tool')
                <div class="box-tools">
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal pull-right"
                        data-href="{{ action([\App\Http\Controllers\LabTestController::class, 'create']) }}"
                        data-container=".lab_test_modal">
                        <i class="fa fa-plus"></i> @lang('messages.add')
                    </a>
                </div>
            @endslot

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="lab_tests_table">
                    <thead>
                        <tr>
                            <th>@lang('lang_v1.test_name')</th>
                            <th>@lang('lang_v1.code')</th>
                            <th>@lang('lang_v1.category')</th>
                            <th>@lang('lang_v1.normal_range')</th>
                            <th>@lang('lang_v1.unit')</th>
                            <th>@lang('lang_v1.fee')</th>
                            <th>@lang('lang_v1.turnaround')</th>
                            <th>@lang('lang_v1.status')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcomponent

        <div class="modal fade lab_test_modal" tabindex="-1" role="dialog"></div>
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    var lab_tests_table = $('#lab_tests_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ action([\App\Http\Controllers\LabTestController::class, 'index']) }}',
        columns: [
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'category', name: 'category' },
            { data: 'normal_range', name: 'normal_range' },
            { data: 'unit', name: 'unit' },
            { data: 'fee', name: 'fee' },
            { data: 'turnaround_hours', name: 'turnaround_hours' },
            { data: 'is_active', name: 'is_active' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        fnDrawCallback: function () {
            __currency_convert_recursively($('#lab_tests_table'));
        },
    });

    // Create
    $(document).on('submit', 'form#lab_test_add_form', function (e) {
        e.preventDefault();
        var form = $(this);
        $.ajax({
            method: 'POST', url: form.attr('action'), dataType: 'json', data: form.serialize(),
            beforeSend: function () { __disable_submit_button(form.find('button[type="submit"]')); },
            success: function (result) {
                if (result.success === true) {
                    $('div.lab_test_modal').modal('hide');
                    toastr.success(result.msg);
                    lab_tests_table.ajax.reload();
                } else { toastr.error(result.msg); }
            },
        });
    });

    // Edit
    $(document).on('click', 'button.edit_lab_test_button', function () {
        $('div.lab_test_modal').load($(this).data('href'), function () {
            $(this).modal('show');
            $('form#lab_test_edit_form').submit(function (e) {
                e.preventDefault();
                var form = $(this);
                $.ajax({
                    method: 'POST', url: form.attr('action'), dataType: 'json', data: form.serialize(),
                    beforeSend: function () { __disable_submit_button(form.find('button[type="submit"]')); },
                    success: function (result) {
                        if (result.success === true) {
                            $('div.lab_test_modal').modal('hide');
                            toastr.success(result.msg);
                            lab_tests_table.ajax.reload();
                        } else { toastr.error(result.msg); }
                    },
                });
            });
        });
    });

    // Delete
    $(document).on('click', 'button.delete_lab_test_button', function () {
        var href = $(this).data('href');
        swal({ title: LANG.sure, icon: 'warning', buttons: true, dangerMode: true }).then(function (yes) {
            if (yes) {
                $.ajax({
                    method: 'DELETE', url: href, dataType: 'json',
                    success: function (result) {
                        if (result.success === true) { toastr.success(result.msg); lab_tests_table.ajax.reload(); }
                        else { toastr.error(result.msg); }
                    },
                });
            }
        });
    });
});
</script>
@endsection
