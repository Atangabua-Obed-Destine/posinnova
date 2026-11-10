@component('components.widget', ['class' => 'box-primary', 'title' => ($show == 'waiting' ? __('clinic.rx_waiting_tab') : __('clinic.rx_done_tab')) . ' (' . $rows->count() . ')'])
    @forelse ($rows as $r)
        @php $partial = $show == 'waiting' && ($r->waiting_count < $r->line_count || $r->sale_count > 0); @endphp
        <div class="dp-row {{ $partial ? 'is-partial' : '' }}">
            <div class="dp-icon"><i class="fas fa-prescription-bottle-alt"></i></div>
            <div class="dp-main">
                <div class="dp-name">{{ $r->patient_name }}
                    @if ($r->file_number)<small class="text-muted">· {{ $r->file_number }}</small>@endif
                </div>
                <div class="dp-meta">
                    <i class="fas fa-user-md"></i> {{ $r->doctor_name ?: '-' }}
                    · {{ @format_datetime($r->updated_at) }}
                    @if ($r->ref_no) · {{ $r->ref_no }}@endif
                </div>
                <div style="margin-top:4px;">
                    @if ($show == 'waiting')
                        <span class="label label-warning">{{ __('clinic.rx_lines_waiting', ['n' => $r->waiting_count, 'total' => $r->line_count]) }}</span>
                        @if ($partial)
                            <span class="label label-info">@lang('clinic.rx_state_partly_given')</span>
                        @endif
                    @else
                        <span class="label label-success">{{ __('clinic.rx_lines_done', ['total' => $r->line_count]) }}</span>
                    @endif
                    @if ($r->sale_count > 0)
                        <span class="text-muted"><i class="fas fa-receipt"></i> {{ trans_choice('clinic.rx_sales_count', $r->sale_count, ['n' => $r->sale_count]) }}</span>
                    @endif
                </div>
            </div>
            <div class="dp-actions">
                @if ($show == 'waiting' && auth()->user()->can('sell.create'))
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}?prescription_id={{ $r->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                        <i class="fas fa-cash-register"></i> @lang('clinic.rx_dispense')
                    </a>
                @endif
                <a href="{{ action([\App\Http\Controllers\ClinicDispensaryController::class, 'show'], [$r->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fas fa-list"></i> @lang('clinic.rx_details')
                </a>
            </div>
        </div>
    @empty
        <div class="dp-empty">
            <i class="fas fa-check-circle fa-2x" style="color:#16a34a;"></i>
            <p style="margin-top:8px;">{{ $show == 'waiting' ? __('clinic.rx_none_waiting') : __('clinic.rx_none_done') }}</p>
        </div>
    @endforelse
@endcomponent
