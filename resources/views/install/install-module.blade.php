@extends('layouts.install')
@section('title', __('innova_setup.install_title'))

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <br/><br/>
            <div class="box box-primary active">
                <div class="box-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{!! session('error') !!}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- PosInnova: no licence to enter; the module's own installer still expects these two fields --}}
                    <form class="form" id="details_form" method="post" action="{{ $action_url }}">
                        {{ csrf_field() }}
                        <input type="hidden" name="license_code" value="I-NNOVA">
                        <input type="hidden" name="login_username" value="I-NNOVA">

                        <h2>{{ $module_display_name }}</h2>
                        <p class="text-muted">{{ config('app.name', 'I-NNOVA POS') }}</p>
                        <hr/>
                        <button type="submit" id="install_button" class="btn btn-primary pull-right">@lang('lang_v1.install')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function () {
            $('form#details_form').submit(function () {
                $('button#install_button').attr('disabled', true);
            });
        });
    </script>
@endsection
