@extends('layouts.app')
@section('title', __('lang_v1.department_issues'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.department_issues')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.issue_stock_to_hospital_departments')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_department_issues')])
            @can('department_issue.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                           href="{{ action([\App\Http\Controllers\DepartmentIssueController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.new_department_issue')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="department_issues_table">
                    <thead>
                        <tr>
                            <th>@lang('messages.date')</th>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.location')</th>
                            <th>@lang('lang_v1.department')</th>
                            <th>@lang('lang_v1.issued_by')</th>
                            <th>@lang('sale.status')</th>
                            <th>@lang('sale.total')</th>
                            <th>@lang('purchase.additional_notes')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcomponent

        <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
    </section>
@endsection

@section('javascript')
    <script src="{{ asset('js/department_issue.js?v=' . $asset_v) }}"></script>
@endsection
