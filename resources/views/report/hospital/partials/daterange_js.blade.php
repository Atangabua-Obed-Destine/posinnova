{{-- Wire a daterangepicker to a table reload. Props: $id (input), $table (var name) --}}
if (typeof dateRangeSettings !== 'undefined') {
    $('#{{ $id }}').daterangepicker(dateRangeSettings, function (start, end) {
        $('#{{ $id }}').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
        {{ $table }}.ajax.reload();
    });
    $('#{{ $id }}').on('cancel.daterangepicker', function () {
        $('#{{ $id }}').val('');
        {{ $table }}.ajax.reload();
    });
}
