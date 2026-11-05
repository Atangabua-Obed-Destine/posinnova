@php
    $pd = isset($contact) ? $contact->patientDetails : null;
    // For create: name parts are not pre-split; for edit we only have the composed name,
    // so individual name parts are left to the user to re-enter via the dedicated fields.
    $blood_groups = ['' => __('messages.please_select'), 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-'];
    $sexes = ['' => __('messages.please_select'), 'M' => __('lang_v1.male'), 'F' => __('lang_v1.female'), 'Other' => __('lang_v1.other')];
@endphp

{{-- Identity --}}
@component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.patient_identity')])
    <div class="row">
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('prefix', __('business.prefix') . ':') !!}
                {!! Form::text('prefix', $contact->prefix ?? null, ['class' => 'form-control', 'placeholder' => __('business.prefix_placeholder')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('first_name', __('business.first_name') . ':*') !!}
                {!! Form::text('first_name', $contact->first_name ?? null, ['class' => 'form-control', 'required', 'placeholder' => __('business.first_name')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('middle_name', __('lang_v1.middle_name') . ':') !!}
                {!! Form::text('middle_name', $contact->middle_name ?? null, ['class' => 'form-control', 'placeholder' => __('lang_v1.middle_name')]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('last_name', __('business.last_name') . ':') !!}
                {!! Form::text('last_name', $contact->last_name ?? null, ['class' => 'form-control', 'placeholder' => __('business.last_name')]) !!}
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('date_of_birth', __('lang_v1.date_of_birth') . ':') !!}
                {!! Form::date('date_of_birth', optional($pd)->date_of_birth ? optional($pd)->date_of_birth->format('Y-m-d') : null, ['class' => 'form-control', 'max' => date('Y-m-d')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sex', __('lang_v1.sex') . ':') !!}
                {!! Form::select('sex', $sexes, optional($pd)->sex, ['class' => 'form-control select2']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('blood_group', __('lang_v1.blood_group') . ':') !!}
                {!! Form::select('blood_group', $blood_groups, optional($pd)->blood_group, ['class' => 'form-control select2']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('known_allergies', __('lang_v1.known_allergies') . ':') !!}
                {!! Form::text('known_allergies', optional($pd)->known_allergies, ['class' => 'form-control', 'placeholder' => __('lang_v1.known_allergies_placeholder')]) !!}
            </div>
        </div>
    </div>
@endcomponent

{{-- Contact details --}}
@component('components.widget', ['class' => 'box-solid', 'title' => __('contact.contact_info')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('mobile', __('contact.mobile') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-mobile"></i></span>
                    {!! Form::text('mobile', $contact->mobile ?? null, ['class' => 'form-control', 'required', 'placeholder' => __('contact.mobile')]) !!}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('alternate_number', __('contact.alternate_contact_number') . ':') !!}
                {!! Form::text('alternate_number', $contact->alternate_number ?? null, ['class' => 'form-control', 'placeholder' => __('contact.alternate_contact_number')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('landline', __('contact.landline') . ':') !!}
                {!! Form::text('landline', $contact->landline ?? null, ['class' => 'form-control', 'placeholder' => __('contact.landline')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('email', __('business.email') . ':') !!}
                {!! Form::email('email', $contact->email ?? null, ['class' => 'form-control', 'placeholder' => __('business.email')]) !!}
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('address_line_1', __('lang_v1.address_line_1') . ':') !!}
                {!! Form::text('address_line_1', $contact->address_line_1 ?? null, ['class' => 'form-control', 'placeholder' => __('lang_v1.address_line_1')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('city', __('business.city') . ':') !!}
                {!! Form::text('city', $contact->city ?? null, ['class' => 'form-control', 'placeholder' => __('business.city')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('state', __('business.state') . ':') !!}
                {!! Form::text('state', $contact->state ?? null, ['class' => 'form-control', 'placeholder' => __('business.state')]) !!}
            </div>
        </div>
    </div>
@endcomponent

{{-- Next of kin --}}
@component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.next_of_kin')])
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('next_of_kin_name', __('lang_v1.next_of_kin_name') . ':') !!}
                {!! Form::text('next_of_kin_name', optional($pd)->next_of_kin_name, ['class' => 'form-control', 'placeholder' => __('lang_v1.next_of_kin_name')]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('next_of_kin_phone', __('lang_v1.next_of_kin_phone') . ':') !!}
                {!! Form::text('next_of_kin_phone', optional($pd)->next_of_kin_phone, ['class' => 'form-control', 'placeholder' => __('lang_v1.next_of_kin_phone')]) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('next_of_kin_relationship', __('lang_v1.next_of_kin_relationship') . ':') !!}
                {!! Form::text('next_of_kin_relationship', optional($pd)->next_of_kin_relationship, ['class' => 'form-control', 'placeholder' => __('lang_v1.next_of_kin_relationship_placeholder')]) !!}
            </div>
        </div>
    </div>
@endcomponent

{{-- Insurance --}}
@component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.insurance')])
    <div class="row">
        <div class="col-md-3">
            {{-- PosInnova clinic phase 5: the insurer is chosen from the insurer list --}}
            @php
                $ins_list = \App\ClinicInsurer::forBusiness(session('user.business_id'))
                    ->where(function ($q) use ($pd) { $q->where('is_active', 1)->orWhere('id', optional($pd)->insurer_id ?: 0); })
                    ->orderBy('name')->get();
            @endphp
            <div class="form-group">
                {!! Form::label('insurer_id', __('lang_v1.insurance_provider') . ':') !!}
                <select name="insurer_id" id="insurer_id" class="form-control">
                    <option value="">@lang('clinic.ins_none')</option>
                    @foreach ($ins_list as $ins)
                        <option value="{{ $ins->id }}" data-pct="{{ is_null($ins->default_pct) ? '' : \App\Utils\ClinicInsurance::pct($ins->default_pct) }}" {{ old('insurer_id', optional($pd)->insurer_id) == $ins->id ? 'selected' : '' }}>{{ $ins->name }}</option>
                    @endforeach
                </select>
                @if ($ins_list->isEmpty())
                    <p class="help-block">@lang('clinic.ins_none_defined')
                        @can('clinic_insurance.manage')<a href="{{ action([\App\Http\Controllers\ClinicInsurerController::class, 'create']) }}" target="_blank">@lang('clinic.ins_add_insurer')</a>@endcan
                    </p>
                @endif
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('insurance_number', __('lang_v1.insurance_number') . ':') !!}
                {!! Form::text('insurance_number', optional($pd)->insurance_number, ['class' => 'form-control', 'placeholder' => __('lang_v1.insurance_number')]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('insurance_coverage_pct', __('lang_v1.insurance_coverage_pct') . ':') !!}
                <div class="input-group">
                    {!! Form::text('insurance_coverage_pct', optional($pd)->insurance_coverage_pct, ['class' => 'form-control input_number', 'id' => 'insurance_coverage_pct', 'placeholder' => __('clinic.ins_pct_default_ph')]) !!}
                    <span class="input-group-addon">%</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('insurance_expiry_date', __('lang_v1.insurance_expiry_date') . ':') !!}
                {!! Form::date('insurance_expiry_date', optional($pd)->insurance_expiry_date ? optional($pd)->insurance_expiry_date->format('Y-m-d') : null, ['class' => 'form-control']) !!}
            </div>
        </div>
    </div>
@endcomponent

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('insurer_id'), pct = document.getElementById('insurance_coverage_pct');
        if (!sel || !pct) { return; }
        var base = pct.getAttribute('placeholder');
        function sync() {
            var d = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].getAttribute('data-pct') : '';
            pct.setAttribute('placeholder', d ? @json(__('clinic.ins_pct_insurer_default')).replace(':pct', d) : base);
        }
        sel.addEventListener('change', sync);
        sync();
    });
</script>

{{-- Notes --}}
@component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.additional_notes')])
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::textarea('notes', optional($pd)->notes, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('lang_v1.additional_notes')]) !!}
            </div>
        </div>
    </div>
@endcomponent

{{-- PosInnova: patient portal PIN. Registering sets the first one (left blank,
     one is generated); on edit only a role with "Set and reset portal PINs"
     sees the field, and a blank field keeps the current PIN. --}}
@if (empty($pd) || auth()->user()->can('patient.reset_portal_pin'))
@component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.portal_pin')])
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('portal_pin', (empty($pd) ? __('clinic.portal_pin') : __('clinic.portal_pin_new')) . ':') !!}
                <div class="input-group">
                    {!! Form::text('portal_pin', null, ['class' => 'form-control', 'id' => 'portal_pin', 'inputmode' => 'numeric', 'pattern' => '[0-9]{4,6}', 'maxlength' => 6, 'autocomplete' => 'off', 'placeholder' => '4 - 6']) !!}
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-default" id="portal_pin_generate">@lang('clinic.portal_pin_generate')</button>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <p class="help-block" style="margin-top:28px;">
                @lang('clinic.portal_pin_help')
                @if (! empty($pd))
                    <br><strong>{{ ! empty($pd->portal_pin) ? __('clinic.portal_pin_set') : __('clinic.portal_pin_not_set') }}</strong>
                    @if (! empty($pd->portal_locked_until) && \Carbon::parse($pd->portal_locked_until)->isFuture())
                        · {{ __('clinic.portal_locked', ['time' => \Carbon::parse($pd->portal_locked_until)->format('H:i')]) }}
                    @endif
                    <br>@lang('clinic.portal_pin_unlocked_note')
                @endif
            </p>
        </div>
    </div>
    <script>
        document.getElementById('portal_pin_generate').addEventListener('click', function () {
            var a = new Uint32Array(1);
            (window.crypto || window.msCrypto).getRandomValues(a);
            document.getElementById('portal_pin').value = String(1000 + (a[0] % 9000));
        });
    </script>
@endcomponent
@endif
