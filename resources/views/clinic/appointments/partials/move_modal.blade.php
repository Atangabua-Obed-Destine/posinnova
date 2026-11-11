{{--
    "Move" dialog plus the scripts every appointment button needs (reason prompt
    on cancel / decline, WhatsApp opened = reminded, move). Needs $access and
    $all_doctors; $from, $doctor_param and $days are posted back.
--}}
@php $A = \App\Http\Controllers\ClinicAppointmentController::class; @endphp
@if ($access['manage_all'] || $access['own'] !== null)
<div class="modal fade" id="move_modal" tabindex="-1" role="dialog" aria-labelledby="move_title">
    <div class="modal-dialog" role="document">
        <form method="POST" action="#" id="move_form" class="modal-content">
            @csrf
            <input type="hidden" name="from" value="{{ $from ?? 'diary' }}">
            <input type="hidden" name="doctor_filter" value="{{ $doctor_param ?? '' }}">
            <input type="hidden" name="days" value="{{ $days ?? '' }}">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}">&times;</button>
                <h4 class="modal-title" id="move_title">@lang('clinic.appt_move_title')</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="margin:0 0 14px;">@lang('clinic.appt_move_now') <strong id="move_current"></strong></p>
                @if ($access['manage_all'])
                    <div class="form-group">
                        <label for="move_doctor">@lang('clinic.doctor'):</label>
                        <select name="doctor_id" id="move_doctor" class="form-control">
                            @foreach ($all_doctors as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="doctor_id" id="move_doctor">
                @endif
                <div class="form-group">
                    <label for="move_date">@lang('lang_v1.date'):</label>
                    <input type="date" name="date" id="move_date" class="form-control" min="{{ \Carbon::today()->toDateString() }}" required>
                </div>
                <div class="form-group">
                    <label for="move_start">@lang('clinic.appt_new_time'):</label>
                    <select name="start" id="move_start" class="form-control" required disabled></select>
                </div>
                <p class="help-block">@lang('clinic.appt_move_help')</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="tw-dw-btn tw-dw-btn-outline" data-dismiss="modal">@lang('messages.cancel')</button>
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white"><i class="fas fa-exchange-alt"></i> @lang('clinic.appt_move')</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
    $(function () {
        // cancel / decline ask for a short reason
        $(document).on('submit', 'form.js-reason', function () {
            var r = prompt($(this).data('prompt'), '');
            if (r === null) { return false; }
            $(this).find('input[name=reason]').val(r);
        });
        // opening WhatsApp records that the reminder was sent
        $(document).on('click', '.js-wa', function () {
            $.post($(this).data('url'), { _token: '{{ csrf_token() }}' });
        });

        var moveUrl = @json(action([$A, 'move'], ['__ID__'])),
            slotsUrl = @json(action([$A, 'freeSlots'])),
            T = { loading: @json(__('clinic.appt_slots_loading')), pick: @json(__('clinic.appt_pick_time')),
                  none: @json(__('clinic.appt_no_free_slots')), off: @json(__('clinic.appt_day_off')) },
            seq = 0;
        function loadSlots() {
            var doctor = $('#move_doctor').val(), date = $('#move_date').val(), $s = $('#move_start'), mine = ++seq;
            $s.prop('disabled', true).empty().append($('<option value="">').text(T.loading));
            if (!doctor || !date) { $s.empty(); return; }
            $.getJSON(slotsUrl, { doctor_id: doctor, date: date }).done(function (r) {
                if (mine !== seq) { return; } // an older answer: the user has changed the day or doctor since
                $s.empty();
                if (!r.slots || !r.slots.length) { $s.append($('<option value="">').text(r.off ? T.off : T.none)); return; }
                $s.append($('<option value="">').text(T.pick));
                r.slots.forEach(function (x) { $s.append($('<option>').val(x.start).text(x.start + ' – ' + x.end)); });
                $s.prop('disabled', false);
            }).fail(function () { if (mine === seq) { $s.empty().append($('<option value="">').text(T.none)); } });
        }
        $(document).on('click', '.js-move', function () {
            var b = $(this);
            $('#move_form').attr('action', moveUrl.replace('__ID__', b.data('id')));
            $('#move_doctor').val(String(b.data('doctor')));
            $('#move_date').val(b.data('date'));
            $('#move_title').text(@json(__('clinic.appt_move_title')) + ' · ' + b.data('patient'));
            $('#move_current').text(b.data('current'));
            loadSlots();
            $('#move_modal').modal('show');
        });
        $(document).on('change', '#move_doctor, #move_date', loadSlots);
    });
</script>
