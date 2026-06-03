$(document).ready(function() {

    if ($('#department_issue_report_filter_form').length === 0) {
        return;
    }

    // Helper: build common filter payload from the form
    function dir_filters(extra) {
        var data = {
            location_id: $('#department_issue_report_filter_form select#location_id').val() || '',
            department_id: $('#department_issue_report_filter_form select#department_id').val() || '',
            status: $('#department_issue_report_filter_form select#status').val() || '',
        };
        if ($('#dir_date_filter').val()) {
            data.start_date = $('#dir_date_filter').data('daterangepicker').startDate.format('YYYY-MM-DD');
            data.end_date = $('#dir_date_filter').data('daterangepicker').endDate.format('YYYY-MM-DD');
        }
        if (extra) {
            $.each(extra, function(k, v) { data[k] = v; });
        }
        return data;
    }

    // Date range picker
    if ($('#dir_date_filter').length == 1 && typeof dateRangeSettings !== 'undefined') {
        $('#dir_date_filter').daterangepicker(dateRangeSettings, function(start, end) {
            $('#dir_date_filter').val(
                start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
            );
            dir_reload_all();
        });
        $('#dir_date_filter').on('cancel.daterangepicker', function() {
            $('#dir_date_filter').val('');
            dir_reload_all();
        });
    }

    // Vouchers DataTable
    var dir_vouchers_table = $('#dir_vouchers_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader: false,
        aaSorting: [[0, 'desc']],
        ajax: {
            url: '/reports/department-issue/details',
            data: function(d) { $.extend(d, dir_filters()); },
        },
        columns: [
            { data: 'transaction_date', name: 'transactions.transaction_date' },
            { data: 'ref_no', name: 'transactions.ref_no' },
            { data: 'location_name', name: 'bl.name' },
            { data: 'department_name', name: 'd.name' },
            { data: 'issued_by', name: 'u.first_name' },
            { data: 'status', name: 'transactions.status' },
            { data: 'final_total', name: 'transactions.final_total' },
            { data: 'additional_notes', name: 'transactions.additional_notes' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        fnDrawCallback: function() {
            __currency_convert_recursively($('#dir_vouchers_table'));
        },
    });

    // By Department
    var dir_by_dept_table = $('#dir_by_dept_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader: false,
        aaSorting: [[4, 'desc']],
        ajax: {
            url: '/reports/department-issue/by-department',
            data: function(d) { $.extend(d, dir_filters()); },
        },
        columns: [
            { data: 'department_name', name: 'd.name' },
            { data: 'hod_name', name: 'd.hod_name', orderable: false },
            { data: 'total_issues', name: 'total_issues', orderable: false, searchable: false },
            { data: 'total_quantity', name: 'total_quantity', orderable: false, searchable: false },
            { data: 'total_amount', name: 'total_amount', orderable: false, searchable: false },
        ],
        fnDrawCallback: function() {
            __currency_convert_recursively($('#dir_by_dept_table'));
        },
    });

    // By Product
    var dir_by_product_table = $('#dir_by_product_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader: false,
        aaSorting: [[4, 'desc']],
        ajax: {
            url: '/reports/department-issue/by-product',
            data: function(d) { $.extend(d, dir_filters()); },
        },
        columns: [
            { data: 'product_name', name: 'p.name' },
            { data: 'sub_sku', name: 'v.sub_sku' },
            { data: 'issue_count', name: 'issue_count', orderable: false, searchable: false },
            { data: 'total_quantity', name: 'total_quantity', orderable: false, searchable: false },
            { data: 'total_amount', name: 'total_amount', orderable: false, searchable: false },
        ],
        fnDrawCallback: function() {
            __currency_convert_recursively($('#dir_by_product_table'));
        },
    });

    // By Period
    var dir_by_period_table = $('#dir_by_period_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader: false,
        aaSorting: [],
        ajax: {
            url: '/reports/department-issue/by-period',
            data: function(d) {
                $.extend(d, dir_filters({ group_by: $('#dir_group_by').val() }));
            },
        },
        columns: [
            { data: 'period', name: 'period', orderable: false, searchable: false },
            { data: 'total_issues', name: 'total_issues', orderable: false, searchable: false },
            { data: 'total_amount', name: 'total_amount', orderable: false, searchable: false },
        ],
        fnDrawCallback: function() {
            __currency_convert_recursively($('#dir_by_period_table'));
        },
    });

    // Summary cards
    function dir_load_summary() {
        $.get('/reports/department-issue', $.extend({ summary: 1 }, dir_filters()), function(res) {
            $('#dir_total_issues').text(res.total_issues);
            $('#dir_total_amount').text(res.total_amount).attr('data-orig-value', res.total_amount);
            $('#dir_total_issued').text(res.total_issued).attr('data-orig-value', res.total_issued);
            $('#dir_department_count').text(res.department_count);
            __currency_convert_recursively($('.content'));
        }, 'json');
    }

    function dir_reload_all() {
        dir_vouchers_table.ajax.reload(null, false);
        dir_by_dept_table.ajax.reload(null, false);
        dir_by_product_table.ajax.reload(null, false);
        dir_by_period_table.ajax.reload(null, false);
        dir_load_summary();
    }

    // Filter changes
    $('#department_issue_report_filter_form select#location_id, ' +
      '#department_issue_report_filter_form select#department_id, ' +
      '#department_issue_report_filter_form select#status').change(function() {
        dir_reload_all();
    });

    $('#dir_group_by').change(function() {
        dir_by_period_table.ajax.reload(null, false);
    });

    // Initial summary load
    dir_load_summary();
});
