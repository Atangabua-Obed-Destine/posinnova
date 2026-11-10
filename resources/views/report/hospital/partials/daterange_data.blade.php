{{-- Append start_date/end_date to a DataTables ajax payload. Prop: $id --}}
if ($('#{{ $id }}').val()) {
    var dr_{{ $id }} = $('#{{ $id }}').data('daterangepicker');
    d.start_date = dr_{{ $id }}.startDate.format('YYYY-MM-DD');
    d.end_date = dr_{{ $id }}.endDate.format('YYYY-MM-DD');
}
