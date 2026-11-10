@extends('layouts.auth2')
@section('title', __('lang_v1.register'))
@section('right-max-width', 'auth-form--xl')

@section('content')

    <div class="tw-w-full">
        <div class="ia-head">
            <div class="ia-head-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10l9-6.5 9 6.5"/><path d="M5 9v11h14V9"/><path d="M10 20v-6h4v6"/></svg>
            </div>
            <h1>@lang('lang_v1.auth_reg_title')</h1>
            <p>@lang('business.register_and_get_started_in_minutes')</p>
        </div>

        <div class="ia-card ia-card--reg">
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
