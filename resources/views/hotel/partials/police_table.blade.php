{{-- PosInnova hotel: the police register (one line per guest present that day) --}}
@if ($cards->isEmpty())
    <div class="ht-empty" style="padding:10px;">{{ __('hotel.nobody_in') }}</div>
@else
    <div class="ht-scroll"><table class="ht-t" id="ht_police">
        <thead><tr><th>{{ __('hotel.room') }}</th><th>{{ __('hotel.f_surname') }}</th><th>{{ __('hotel.born') }}</th><th>{{ __('hotel.f_nationality') }}</th><th>{{ __('hotel.f_profession') }}</th>
            <th>{{ __('hotel.id_document') }}</th><th>{{ __('hotel.from_to') }}</th><th>{{ __('hotel.arrived') }}</th><th>{{ __('hotel.leaves') }}</th></tr></thead>
        <tbody>
        @foreach ($cards as $g)
            <tr>
                <td>{{ optional($g->res->room)->number }}</td>
                <td><strong>{{ $g->fullName() }}</strong>@if ($g->sex) <span class="ht-muted">({{ $g->sex }})</span>@endif</td>
                <td>{{ optional($g->dob)->format('d/m/Y') }}{{ $g->birth_place ? ', ' . $g->birth_place : '' }}</td>
                <td>{{ $g->nationality }}</td><td>{{ $g->profession }}</td>
                <td>{{ $g->id_number ? __('hotel.idt_' . ($g->id_type ?: 'other')) . ' ' . $g->id_number : '' }}@if ($g->id_issued_on || $g->id_issued_at)<div class="ht-muted ht-small">{{ optional($g->id_issued_on)->format('d/m/Y') }} {{ $g->id_issued_at }}</div>@endif</td>
                <td>{{ $g->coming_from }}{{ $g->going_to ? ' → ' . $g->going_to : '' }}</td>
                <td>{{ optional($g->res->checked_in_at)->format('d/m H:i') }}</td>
                <td>{{ $g->res->checked_out_at ? $g->res->checked_out_at->format('d/m H:i') : $g->res->departure->format('d/m') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
