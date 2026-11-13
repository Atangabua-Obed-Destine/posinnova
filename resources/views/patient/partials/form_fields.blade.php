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

        {{-- PosInnova: birth date, or the age when the patient does not know the date (the date is then
             estimated from it and marked as such). An estimated date is never shown as if it were real. --}}
        @php
            $dob_est = (bool) optional($pd)->dob_estimated && optional($pd)->date_of_birth;
            $dob_mode = old('dob_mode', $dob_est ? 'age' : 'date');
            $age_cur = null;
            $age_unit_cur = 'years';
            if ($dob_est) {
                $age_months = $pd->date_of_birth->diffInMonths(now());
                if ($age_months < 24) {
                    $age_unit_cur = 'months';
                    $age_cur = $age_months;
                } else {
                    $age_cur = $pd->date_of_birth->age;
                }
            }
            $age_unit_cur = old('age_unit', $age_unit_cur);
        @endphp
        <div class="col-md-3 pf-dob">
            <input type="hidden" name="dob_mode" class="pf-dob-mode" value="{{ $dob_mode }}">
            <div class="form-group pf-dob-date" @if ($dob_mode === 'age') style="display:none;" @endif>
                {!! Form::label('date_of_birth', __('lang_v1.date_of_birth') . ':') !!}
                {!! Form::date('date_of_birth', old('date_of_birth', ! $dob_est && optional($pd)->date_of_birth ? $pd->date_of_birth->format('Y-m-d') : null), ['class' => 'form-control', 'max' => date('Y-m-d')]) !!}
                <a href="#" class="pf-dob-switch" data-to="age" style="font-size:12px;"><i class="fas fa-question-circle"></i> @lang('clinic.pf_dob_unknown')</a>
            </div>
            <div class="form-group pf-dob-age" @if ($dob_mode !== 'age') style="display:none;" @endif>
                <label>@lang('clinic.pf_age'):</label>
                <div style="display:flex; gap:6px;">
                    <input type="number" name="age_value" class="form-control pf-age" min="0" max="150" step="1" inputmode="numeric" value="{{ old('age_value', $age_cur) }}" style="flex:1 1 auto; min-width:0;">
                    <select name="age_unit" class="form-control" style="width:auto; flex:0 0 auto;">
                        <option value="years" {{ $age_unit_cur !== 'months' ? 'selected' : '' }}>@lang('clinic.pf_age_years')</option>
                        <option value="months" {{ $age_unit_cur === 'months' ? 'selected' : '' }}>@lang('clinic.pf_age_months')</option>
                    </select>
                </div>
                <a href="#" class="pf-dob-switch" data-to="date" style="font-size:12px;"><i class="fas fa-calendar-alt"></i> @lang('clinic.pf_dob_known')</a>
                <p class="help-block" style="margin:2px 0 0; font-size:12px;">@lang('clinic.pf_age_help')</p>
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
@component('components.widget', ['class' => 'box-solid', 'title' => __('contact.contact_info', ['contact' => __('lang_v1.patient')])])
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

        {{-- PosInnova: consent to automatic WhatsApp messages about appointments (nothing is sent without it) --}}
        @php
            // a new patient starts ticked (reception still asks, and unticks if the patient says no);
            // an existing patient keeps their answer; a form sent back with an error keeps what was sent
            if (empty($pf_quick) && session()->hasOldInput()) {
                $wa_on = (bool) old('whatsapp_opt_in');
            } elseif (! empty($pd)) {
                $wa_on = (bool) $pd->whatsapp_opt_in;
            } else {
                $wa_on = true;
            }
        @endphp
        <div class="col-md-12">
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:8px 12px; margin-bottom:14px;" id="wa_consent_box">
                <div class="checkbox" style="margin:0;">
                    <label style="font-weight:600;">
                        <input type="checkbox" name="whatsapp_opt_in" value="1" id="whatsapp_opt_in" {{ $wa_on ? 'checked' : '' }}>
                        <i class="fab fa-whatsapp" style="color:#16a34a;"></i> @lang('clinic.wa_consent_label')
                    </label>
                    <span style="display:inline-flex; gap:6px; align-items:center; margin-left:8px;">
                        <label for="whatsapp_language" style="margin:0; font-weight:normal;" class="text-muted">@lang('clinic.wa_consent_lang')</label>
                        <select name="whatsapp_language" id="whatsapp_language" class="form-control input-sm" style="width:auto;">
                            <option value="">@lang('clinic.wa_consent_lang_default')</option>
                            <option value="fr" {{ old('whatsapp_language', optional($pd)->whatsapp_language) == 'fr' ? 'selected' : '' }}>Français</option>
                            <option value="en" {{ old('whatsapp_language', optional($pd)->whatsapp_language) == 'en' ? 'selected' : '' }}>English</option>
                        </select>
                    </span>
                </div>
                <p class="help-block" style="margin:4px 0 0;">
                    @lang('clinic.wa_consent_help')
                    @if (! empty($pd) && $pd->whatsapp_opt_out_at && ! $pd->whatsapp_opt_in)
                        <br><span class="text-warning"><i class="fas fa-ban"></i> {{ __('clinic.wa_consent_stopped', ['date' => @format_date($pd->whatsapp_opt_out_at)]) }}</span>
                    @elseif (! empty($pd) && $pd->whatsapp_opt_in && $pd->whatsapp_opt_in_at)
                        <br><span class="text-success"><i class="fas fa-check"></i> {{ __('clinic.wa_consent_since', ['date' => @format_date($pd->whatsapp_opt_in_at)]) }}</span>
                    @endif
                </p>
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

{{-- PosInnova: Next of kin and Insurance are folded until opened (or when they already hold something) --}}
@php
    $pf_fold_header = function ($title, $key, $open) {
        return '<button type="button" class="pf-fold" data-fold="'.$key.'" aria-expanded="'.($open ? 'true' : 'false').'"'
            .' style="background:none;border:0;padding:0;width:100%;text-align:left;display:flex;align-items:center;gap:8px;cursor:pointer;">'
            .'<h3 class="box-title" style="margin:0;">'.e($title).'</h3>'
            .'<small class="text-muted pf-fold-hint" style="'.($open ? 'display:none;' : '').'">'.e(__('clinic.pf_fold_hint')).'</small>'
            .'<i class="fas fa-chevron-down pf-fold-icon" style="margin-left:auto;transition:transform .2s;'.($open ? 'transform:rotate(180deg);' : '').'"></i>'
            .'</button>';
    };
    $pf_filled = function (array $fields) use ($pd) {
        foreach ($fields as $f) {
            if (filled(old($f, optional($pd)->$f))) {
                return true;
            }
        }

        return false;
    };
    $kin_open = $pf_filled(['next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship']);
    $ins_open = $pf_filled(['insurer_id', 'insurance_number', 'insurance_coverage_pct', 'insurance_expiry_date']);
@endphp

{{-- Next of kin --}}
@component('components.widget', ['class' => 'box-solid', 'header' => $pf_fold_header(__('lang_v1.next_of_kin'), 'kin', $kin_open)])
    <div class="pf-fold-body" data-fold-body="kin" @unless ($kin_open) style="display:none;" @endunless>
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
    </div>
@endcomponent

{{-- Insurance --}}
@component('components.widget', ['class' => 'box-solid', 'header' => $pf_fold_header(__('lang_v1.insurance'), 'ins', $ins_open)])
    <div class="pf-fold-body" data-fold-body="ins" @unless ($ins_open) style="display:none;" @endunless>
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

<script>
    // PosInnova: birth date / age switch and the folded sections. Plain JS on the document, so it also
    // works when this form is in the quick-registration dialog (and is set up once).
    if (! window.__pfFormBound) {
        window.__pfFormBound = true;
        document.addEventListener('click', function (e) {
            var sw = e.target.closest && e.target.closest('.pf-dob-switch');
            if (sw) {
                e.preventDefault();
                var box = sw.closest('.pf-dob'), to = sw.getAttribute('data-to');
                box.querySelector('.pf-dob-mode').value = to;
                box.querySelector('.pf-dob-date').style.display = to === 'date' ? '' : 'none';
                box.querySelector('.pf-dob-age').style.display = to === 'age' ? '' : 'none';
                var f = box.querySelector(to === 'age' ? '.pf-age' : 'input[type=date]');
                if (f) { f.focus(); }
                return;
            }
            var fold = e.target.closest && e.target.closest('.pf-fold');
            if (fold) {
                e.preventDefault();
                var form = fold.closest('form') || document;
                var body = form.querySelector('[data-fold-body="' + fold.getAttribute('data-fold') + '"]');
                if (!body) { return; }
                var open = body.style.display === 'none';
                body.style.display = open ? '' : 'none';
                fold.setAttribute('aria-expanded', open ? 'true' : 'false');
                var icon = fold.querySelector('.pf-fold-icon'), hint = fold.querySelector('.pf-fold-hint');
                if (icon) { icon.style.transform = open ? 'rotate(180deg)' : ''; }
                if (hint) { hint.style.display = open ? 'none' : ''; }
            }
        });
    }
</script>

{{-- Notes --}}
@component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.additional_notes')])
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::textarea('notes', ! empty($pf_quick) ? '' : optional($pd)->notes, ['class' => 'form-control', 'rows' => 3, 'id' => 'patient_notes', 'placeholder' => __('lang_v1.additional_notes')]) !!}
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
