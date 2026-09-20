@extends('layouts.app')
@section('title', __('lang_v1.patients'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.patients')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.manage_patients')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('patient_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('patient_status_filter', ['' => __('lang_v1.all'), 'active' => __('lang_v1.active'), 'inactive' => __('lang_v1.inactive')], '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('patient_sex_filter', __('lang_v1.sex') . ':') !!}
                    {!! Form::select('patient_sex_filter', ['' => __('lang_v1.all'), 'M' => __('lang_v1.male'), 'F' => __('lang_v1.female'), 'Other' => __('lang_v1.other')], '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_patients')])
            @can('patient.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\PatientController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.add_patient')
                        </a>
                    </div>
                @endslot
            @endcan

            @can('patient.view')
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dt-responsive" id="patients_table" style="width:100%">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.file_number')</th>
                                <th>@lang('contact.name')</th>
                                <th>@lang('lang_v1.sex')</th>
                                <th>@lang('lang_v1.age')</th>
                                <th>@lang('lang_v1.blood_group')</th>
                                <th>@lang('contact.mobile')</th>
                                <th>@lang('lang_v1.insurance_provider')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcan
        @endcomponent
    </section>
@endsection

@section('javascript')
    <script>
        $(document).ready(function () {
            var patients_table = $('#patients_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ action([\App\Http\Controllers\PatientController::class, 'index']) }}',
                    data: function (d) {
                        d.contact_status = $('#patient_status_filter').val();
                        d.sex = $('#patient_sex_filter').val();
                    },
                },
                columns: [
                    { data: 'file_number', name: 'pd.file_number' },
                    { data: 'name', name: 'contacts.name' },
                    { data: 'sex', name: 'pd.sex' },
                    { data: 'age', name: 'pd.date_of_birth', searchable: false, orderable: false },
                    { data: 'blood_group', name: 'pd.blood_group' },
                    { data: 'mobile', name: 'contacts.mobile' },
                    { data: 'insurance_provider', name: 'pd.insurance_provider' },
                    { data: 'action', name: 'action', searchable: false, orderable: false },
                ],
                order: [[0, 'desc']],
                responsive: true,
            });

            $('#patient_status_filter, #patient_sex_filter').change(function () {
                patients_table.ajax.reload();
            });

            $(document).on('click', 'a.delete_patient_button', function (e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then(willDelete => {
                    if (willDelete) {
                        $.ajax({
                            method: 'DELETE',
                            url: $(this).attr('href'),
                            dataType: 'json',
                            success: function (result) {
                                if (result.success === true) {
                                    toastr.success(result.msg);
                                    patients_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            },
                        });
                    }
                });
            });
        });
    </script>
@endsection
