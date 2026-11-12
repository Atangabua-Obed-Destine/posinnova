{{--
    PosInnova: hospital / clinic permissions on the Roles screen, grouped by desk.

    Before this partial existed no role screen could grant any hospital
    permission, so only Admins (who bypass permissions) could use the module.

    A group whose hospital section is switched off in Clinic settings is not
    shown, but the permissions the role already holds in it are re-posted as
    hidden fields: RoleController syncs exactly what is posted, so without them
    saving a role would silently strip those permissions.

    Included by role/create and role/edit; $role_permissions is only set on edit.
--}}
@if (in_array('hospital', $enabled_modules))
@php
    // labels are keyed by the permission name, which contains dots: read the array, not __('clinic.perm.x')
    $hp_labels = (array) __('clinic.perm');
    $hp_business_id = session('user.business_id');
    $hp_has = $role_permissions ?? [];
    $hp_on = function ($section) use ($hp_business_id) {
        return \App\Utils\ClinicSettings::sectionOn($hp_business_id, $section);
    };
    $hp_groups = [
        'reception' => [null, ['patient.view', 'patient.create', 'patient.update', 'patient.delete', 'patient.reset_portal_pin', 'visit.view', 'visit.create', 'visit.record_vitals', 'visit.close']],
        'doctor' => [null, ['visit.receive_patients', 'consultation.create', 'consultation.view_own', 'consultation.view_all', 'consultation.prescribe', 'consultation.finalize', 'clinic_report.my_practice', 'appointment.manage_own', 'clinic_schedule.manage_own']],
        'lab' => [null, ['lab_test.manage_catalogue', 'lab_request.view', 'lab_request.create', 'lab_result.enter', 'lab_result.view', 'lab_result.validate', 'lab_result.amend']],
        'imaging' => ['imaging', ['imaging.manage_catalogue', 'imaging_request.view', 'imaging_request.create', 'imaging_result.enter', 'imaging_result.view']],
        'appointments' => [null, ['appointment.view', 'appointment.manage', 'clinic_schedule.manage']],
        'billing' => [null, ['patient.view_bill', 'visit.override_pay_first', 'hospital_charge.void', 'clinic_plan.manage', 'clinic_insurance.manage']],
        'dispensary' => [null, ['prescription.dispense']],
        'admin' => [null, ['clinic_settings.manage', 'clinic_package.manage']],
        'wards' => ['wards', ['admission.view', 'admission.create', 'admission.update', 'admission.discharge', 'ward.manage', 'ward_transfer.create', 'ward_dispense.create', 'nursing.chart_vitals', 'nursing.record_medication', 'nursing.add_notes']],
        'theatre' => ['theatre', ['theatre.view', 'theatre.book', 'theatre.record_procedure', 'theatre.manage_catalogue']],
        'maternity' => ['maternity', ['antenatal.view', 'antenatal.create', 'antenatal.update', 'delivery.record']],
        'morgue' => ['morgue', ['morgue.view', 'morgue.receive', 'morgue.record_visit', 'morgue.release', 'morgue.manage_slots']],
        'departments' => ['departments', ['department.view', 'department.create', 'department.update', 'department.delete', 'department_issue.access', 'department_issue.create', 'department_issue.delete', 'department_issue.view_report']],
        'reports' => [null, array_values(array_filter([
            'clinic_report.all_doctors', 'clinic_report.finance', 'clinic_report.attendance',
            'hospital_report.dashboard', 'hospital_report.opd', 'hospital_report.lab', 'hospital_report.revenue',
            $hp_on('wards') ? 'hospital_report.admissions' : null, $hp_on('wards') ? 'hospital_report.ward' : null,
            $hp_on('morgue') ? 'hospital_report.mortality' : null, $hp_on('morgue') ? 'hospital_report.morgue' : null,
            $hp_on('maternity') ? 'hospital_report.antenatal' : null,
        ]))],
    ];
    // report permissions of switched-off sections, kept like the hidden groups
    $hp_hidden_reports = array_values(array_filter([
        $hp_on('wards') ? null : 'hospital_report.admissions', $hp_on('wards') ? null : 'hospital_report.ward',
        $hp_on('morgue') ? null : 'hospital_report.mortality', $hp_on('morgue') ? null : 'hospital_report.morgue',
        $hp_on('maternity') ? null : 'hospital_report.antenatal',
    ]));
@endphp
@foreach ($hp_groups as $hp_key => [$hp_section, $hp_perms])
    @if ($hp_section !== null && ! $hp_on($hp_section))
        @foreach ($hp_perms as $hp_perm)
            @if (in_array($hp_perm, $hp_has))
                <input type="hidden" name="permissions[]" value="{{ $hp_perm }}">
            @endif
        @endforeach
        @continue
    @endif
    <div class="row check_group">
        <div class="col-md-1">
            <h4>@lang('clinic.perm_group_' . $hp_key)</h4>
        </div>
        <div class="col-md-2">
            <div class="checkbox">
                <label>
                    <input type="checkbox" class="check_all input-icheck"> {{ __('role.select_all') }}
                </label>
            </div>
        </div>
        <div class="col-md-9">
            @foreach ($hp_perms as $hp_perm)
                <div class="col-md-12">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('permissions[]', $hp_perm, in_array($hp_perm, $hp_has), ['class' => 'input-icheck']) !!}
                            {{ $hp_labels[$hp_perm] ?? $hp_perm }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <hr>
@endforeach
@foreach ($hp_hidden_reports as $hp_perm)
    @if (in_array($hp_perm, $hp_has))
        <input type="hidden" name="permissions[]" value="{{ $hp_perm }}">
    @endif
@endforeach
@endif
