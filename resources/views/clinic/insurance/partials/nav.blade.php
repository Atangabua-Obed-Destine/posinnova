<div class="btn-group" style="margin-bottom:12px;">
    <a href="{{ action([\App\Http\Controllers\ClinicClaimController::class, 'index']) }}" class="btn btn-sm {{ $active == 'claims' ? 'btn-primary' : 'btn-default' }}"><i class="fas fa-file-medical"></i> @lang('clinic.ins_claims')</a>
    <a href="{{ action([\App\Http\Controllers\ClinicInsurerController::class, 'index']) }}" class="btn btn-sm {{ $active == 'insurers' ? 'btn-primary' : 'btn-default' }}"><i class="fas fa-building"></i> @lang('clinic.ins_insurers')</a>
</div>
