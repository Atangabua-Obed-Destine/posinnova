@extends('layouts.app')
@section('title', __('lang_v1.visits'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.visits')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.opd_queue_and_visits')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('visit_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('visit_status_filter', ['' => __('lang_v1.all'), 'open' => __('lang_v1.visit_status_open'), 'admitted' => __('lang_v1.visit_status_admitted'), 'closed' => __('lang_v1.visit_status_closed'), 'cancelled' => __('lang_v1.visit_status_cancelled')], 'open', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('visit_type_filter', __('lang_v1.visit_type') . ':') !!}
                    {!! Form::select('visit_type_filter', ['' => __('lang_v1.all'), 'opd' => __('lang_v1.visit_type_opd'), 'inpatient' => __('lang_v1.visit_type_inpatient')], '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('visit_location_filter', __('purchase.business_location') . ':') !!}
                    {!! Form::select('visit_location_filter', $business_locations, '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            @if (! empty($doctors))
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('visit_doctor_filter', __('clinic.doctor') . ':') !!}
                    {!! Form::select('visit_doctor_filter', ['' => __('lang_v1.all')] + $doctors, '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            @endif
            <div class="col-md-3">
                <div class="form-group">
                    <br>
                    <label class="checkbox-inline">
                        {!! Form::checkbox('today_only_filter', 1, true, ['id' => 'today_only_filter']) !!}
                        @lang('lang_v1.today_only')
                    </label>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.visits')])
            @can('visit.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\VisitController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('clinic.check_in_patient')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="visits_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('clinic.queue_no')</th>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.file_number')</th>
                            <th>@lang('lang_v1.patient')</th>
                            <th>@lang('clinic.doctor')</th>
                            <th>@lang('clinic.payment')</th>
                            <th>@lang('lang_v1.visit_type')</th>
                            <th>@lang('lang_v1.date')</th>
                            <th>@lang('lang_v1.status')</th>
                            <th>@lang('lang_v1.opened_by')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcomponent
    </section>
    <div class="modal fade payment_modal" tabindex="-1" role="dialog"></div>
    <div class="modal fade edit_payment_modal" tabindex="-1" role="dialog"></div>
@endsection

@section('javascript')
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
    <script>
        $(document).ready(function () {
            var visits_table = $('#visits_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ action([\App\Http\Controllers\VisitController::class, 'index']) }}',
                    data: function (d) {
                        d.status = $('#visit_status_filter').val();
                        d.visit_type = $('#visit_type_filter').val();
                        d.location_id = $('#visit_location_filter').val();
                        d.doctor_id = $('#visit_doctor_filter').val();
                        d.today_only = $('#today_only_filter').is(':checked') ? 1 : 0;
                    },
                },
                columns: [
                    { data: 'queue_no', name: 'visits.queue_no', searchable: false },
                    { data: 'ref_no', name: 'visits.ref_no' },
                    { data: 'file_number', name: 'pd.file_number' },
                    { data: 'patient_name', name: 'c.name' },
                    { data: 'doctor_name', name: 'doc.first_name', searchable: false },
                    { data: 'payment', name: 'payment', searchable: false, orderable: false },
                    { data: 'type', name: 'visits.type' },
                    { data: 'visit_date', name: 'visits.visit_date' },
                    { data: 'status', name: 'visits.status' },
                    { data: 'opened_by_name', name: 'u.first_name' },
                    { data: 'action', name: 'action', searchable: false, orderable: false },
                ],
                order: [[7, 'desc'], [0, 'asc']],
                responsive: true,
            });

            $('#visit_status_filter, #visit_type_filter, #visit_location_filter, #visit_doctor_filter, #today_only_filter').change(function () {
                visits_table.ajax.reload();
            });

            //PosInnova clinic: cancel a check-in (voids an unpaid check-in invoice)
            $(document).on('click', 'a.cancel_checkin_button', function (e) {
                e.preventDefault();
                var url = $(this).attr('href');
                swal({
                    title: LANG.sure,
                    text: {!! json_encode(__('clinic.cancel_checkin_confirm')) !!},
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then(confirmed => {
                    if (confirmed) {
                        $.ajax({
                            method: 'POST',
                            url: url,
                            dataType: 'json',
                            data: {_token: $('meta[name="csrf-token"]').attr('content')},
                            success: function (result) {
                                if (result.success) {
                                    toastr.success(result.msg);
                                    visits_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });

            $(document).on('click', 'a.close_visit_button', function (e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: '{{ __('lang_v1.close_visit_confirm') }}',
                    icon: 'warning',
                    buttons: true,
                }).then(confirmed => {
                    if (confirmed) {
                        //PosInnova: closing a visit is a POST (CSRF-protected), not a link
                        $.ajax({
                            method: 'POST',
                            url: $(this).attr('href'),
                            dataType: 'json',
                            data: {_token: $('meta[name="csrf-token"]').attr('content')},
                            success: function (result) {
                                if (result.success) {
                                    toastr.success(result.msg);
                                    visits_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
