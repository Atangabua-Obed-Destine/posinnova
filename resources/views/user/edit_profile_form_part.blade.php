<div class="row">
    <div class="col-md-12">
        @component('components.widget', ['title' => __('lang_v1.more_info')])
            @slot('tool')
                <button type="button" class="btn btn-default btn-sm pull-right" data-toggle="collapse" data-target="#more_info_collapse" aria-expanded="false" aria-controls="more_info_collapse">
                    <i class="fa fa-chevron-down"></i>
                </button>
            @endslot
            <div id="more_info_collapse" class="collapse">
                @include('user.form')
            </div>
        @endcomponent
    </div>
</div>
