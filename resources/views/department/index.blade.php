@extends('layouts.app')
@section('title', __('lang_v1.departments'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.departments')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.manage_hospital_departments')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_departments')])
            @can('department.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal pull-right"
                            data-href="{{ action([\App\Http\Controllers\DepartmentController::class, 'create']) }}"
                            data-container=".departments_modal">
                            <i class="fa fa-plus"></i> @lang('messages.add')
                        </a>
                    </div>
                @endslot
            @endcan

            @can('department.view')
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="departments_table">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.department_name')</th>
                                <th>@lang('lang_v1.code')</th>
                                <th>@lang('lang_v1.hod_name')</th>
                                <th>@lang('lang_v1.description')</th>
                                <th>@lang('lang_v1.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcan
        @endcomponent

        <div class="modal fade departments_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
    </section>
@endsection

@section('javascript')
    <script src="{{ asset('js/department.js?v=' . $asset_v) }}"></script>
@endsection
