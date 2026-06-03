@extends('layouts.auth2')
@section('title', __('lang_v1.register'))
@section('right-max-width', 'auth-form--xl')

@section('content')

    <div class="tw-w-full">
        <div class="tw-flex tw-flex-col tw-items-center tw-mb-6">
            <div class="tw-w-16 tw-h-16 tw-rounded-2xl tw-bg-gradient-to-br tw-from-blue-500 tw-to-indigo-600 tw-flex tw-items-center tw-justify-center tw-shadow-lg tw-mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="tw-w-8 tw-h-8 tw-text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9.5L12 3l9 6.5V21H3z"/><path d="M9 21V12h6v9"/>
                </svg>
            </div>
            <h1 class="tw-text-2xl sm:tw-text-3xl tw-font-bold tw-text-gray-900 tw-text-center">{{ config('app.name', 'POSinnova') }}</h1>
            <p class="tw-mt-1 tw-text-sm tw-text-gray-500 tw-text-center">@lang('business.register_and_get_started_in_minutes')</p>
        </div>

        <div class="tw-bg-white tw-rounded-2xl tw-shadow-xl tw-ring-1 tw-ring-gray-100 tw-p-6 sm:tw-p-8">
            {!! Form::open([
                'url' => route('business.postRegister'),
                'method' => 'post',
                'id' => 'business_register_form',
                'files' => true,
            ]) !!}
            @include('business.partials.register_form', ['is_register' => true])
            {!! Form::hidden('package_id', $package_id) !!}
            {!! Form::close() !!}
        </div>
    </div>
@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('.change_lang').click(function() {
                window.location = "{{ route('business.getRegister') }}?lang=" + $(this).attr('value');
            });
        })
    </script>
@endsection
