$(document).ready(function() {

    // ============ INDEX PAGE (list) ============
    if ($('#department_issues_table').length > 0) {
        var department_issues_table = $('#department_issues_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[0, 'desc']],
            ajax: '/department-issues',
            columnDefs: [
                { targets: 8, orderable: false, searchable: false },
            ],
            columns: [
                { data: 'transaction_date', name: 'transactions.transaction_date' },
                { data: 'ref_no', name: 'transactions.ref_no' },
                { data: 'location_name', name: 'bl.name' },
                { data: 'department_name', name: 'd.name' },
                { data: 'issued_by', name: 'u.first_name' },
                { data: 'status', name: 'transactions.status' },
                { data: 'final_total', name: 'transactions.final_total' },
                { data: 'additional_notes', name: 'transactions.additional_notes' },
                { data: 'action', name: 'action' },
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#department_issues_table'));
            },
        });

        $(document).on('click', 'button.delete_department_issue', function() {
            var href = $(this).data('href');
            swal({
                title: LANG.sure,
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(function(willDelete) {
                if (willDelete) {
                    $.ajax({
                        method: 'DELETE',
                        url: href,
                        dataType: 'json',
                        success: function(result) {
                            if (result.success) {
                                toastr.success(result.msg);
                                department_issues_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        },
                    });
                }
            });
        });
    }

    // ============ CREATE PAGE (form) ============
    if ($('#department_issue_form').length === 0) {
        return;
    }

    // Date picker
    $('#transaction_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });

    // Product autocomplete (uses location_id)
    $('#search_product_for_srock_adjustment').autocomplete({
        source: function(request, response) {
            $.getJSON(
                '/products/list',
                { location_id: $('#location_id').val(), term: request.term },
                response
            );
        },
        minLength: 2,
        response: function(event, ui) {
            if (ui.content.length === 1) {
                ui.item = ui.content[0];
                if (ui.item.qty_available > 0 && ui.item.enable_stock == 1) {
                    $(this).data('ui-autocomplete')._trigger('select', 'autocompleteselect', ui);
                    $(this).autocomplete('close');
                }
            } else if (ui.content.length === 0) {
                swal(LANG.no_products_found);
            }
        },
        focus: function(event, ui) {
            if (ui.item.qty_available <= 0) { return false; }
        },
        select: function(event, ui) {
            if (ui.item.qty_available > 0) {
                $(this).val(null);
                department_issue_product_row(ui.item.variation_id);
            } else {
                alert(LANG.out_of_stock);
            }
        },
    }).autocomplete('instance')._renderItem = function(ul, item) {
        if (item.qty_available <= 0) {
            var s = '<li class="ui-state-disabled">' + item.name;
            if (item.type === 'variable') { s += '-' + item.variation; }
            s += ' (' + item.sub_sku + ') (Out of stock) </li>';
            return $(s).appendTo(ul);
        } else if (item.enable_stock != 1) {
            return ul;
        }
        var s = '<div>' + item.name;
        if (item.type === 'variable') { s += '-' + item.variation; }
        s += ' (' + item.sub_sku + ') </div>';
        return $('<li>').append(s).appendTo(ul);
    };

    $('select#location_id').change(function() {
        if ($(this).val()) {
            $('#search_product_for_srock_adjustment').removeAttr('disabled');
        } else {
            $('#search_product_for_srock_adjustment').attr('disabled', 'disabled');
        }
        $('table#stock_adjustment_product_table tbody').html('');
        $('#product_row_index').val(0);
        update_dept_issue_total();
    });

    $(document).on('change', 'input.product_quantity, input.product_unit_price', function() {
        update_dept_issue_row($(this).closest('tr'));
    });

    $(document).on('click', '.remove_product_row', function() {
        var row = $(this).closest('tr');
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(function(willDelete) {
            if (willDelete) {
                row.remove();
                update_dept_issue_total();
            }
        });
    });

    $(document).on('change', 'select.sub_unit', function() {
        var tr = $(this).closest('tr');
        var selected_option = $(this).find(':selected');
        var multiplier = parseFloat(selected_option.data('multiplier')) || 1;
        var allow_decimal = parseInt(selected_option.data('allow_decimal'));

        tr.find('input.base_unit_multiplier').val(multiplier);

        // Re-price the displayed unit_price = base_purchase_price * multiplier
        var base_unit_price = parseFloat(tr.find('input.hidden_base_unit_price').val()) || 0;
        var unit_price = base_unit_price * multiplier;
        __write_number(tr.find('input.product_unit_price'), unit_price);
        tr.find('.unit_price_per_base').text(__number_f(base_unit_price));

        // Recompute max-available qty in the selected sub-unit
        var qty_element = tr.find('input.product_quantity');
        var base_max_avlbl = qty_element.data('qty_available');
        var error_msg_line = 'pos_max_qty_error';

        if (tr.find('select.lot_number').length > 0) {
            var lot_select = tr.find('select.lot_number');
            if (lot_select.val()) {
                base_max_avlbl = lot_select.find(':selected').data('qty_available');
                error_msg_line = 'lot_max_qty_error';
            }
        }
        qty_element.attr('data-decimal', allow_decimal);
        if (qty_element.rules) {
            qty_element.rules('add', { abs_digit: !allow_decimal });
        }
        if (base_max_avlbl) {
            var max_avlbl = parseFloat(base_max_avlbl) / multiplier;
            var unit_name = selected_option.data('unit_name');
            var max_err_msg = (typeof __translate === 'function')
                ? __translate(error_msg_line, { max_val: __number_f(max_avlbl), unit_name: unit_name })
                : 'Max available: ' + __number_f(max_avlbl) + ' ' + unit_name;
            qty_element.attr('data-rule-max-value', max_avlbl);
            qty_element.attr('data-msg-max-value', max_err_msg);
            if (qty_element.rules) {
                qty_element.rules('add', {
                    'max-value': max_avlbl,
                    messages: { 'max-value': max_err_msg },
                });
            }
        }
        if (qty_element.valid) { qty_element.valid(); }

        update_dept_issue_row(tr);
    });

    $('#save_department_issue').click(function(e) {
        e.preventDefault();
        if ($('table#stock_adjustment_product_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }
        if (! $('#department_id').val()) {
            toastr.warning('Please select a department');
            return false;
        }
        if ($('form#department_issue_form').valid()) {
            $('form#department_issue_form').submit();
        }
    });
});

function department_issue_product_row(variation_id) {
    var row_index = parseInt($('#product_row_index').val());
    var location_id = $('select#location_id').val();
    $.ajax({
        method: 'POST',
        url: '/stock-adjustments/get_product_row',
        data: { row_index: row_index, variation_id: variation_id, location_id: location_id, type: 'stock_transfer' },
        dataType: 'html',
        success: function(result) {
            $('table#stock_adjustment_product_table tbody').append(result);
            update_dept_issue_total();
            $('#product_row_index').val(row_index + 1);
        },
    });
}

function update_dept_issue_row(row) {
    var qty = parseFloat(__read_number(row.find('input.product_quantity'))) || 0;
    var price = parseFloat(__read_number(row.find('input.product_unit_price'))) || 0;
    // unit_price already reflects the selected sub-unit (= base_price * multiplier),
    // so line total = qty (in sub-unit) * unit_price.
    var total = qty * price;
    __write_number(row.find('input.product_line_total'), total);
    update_dept_issue_total();
}

function update_dept_issue_total() {
    var grand = 0;
    $('table#stock_adjustment_product_table tbody tr').each(function() {
        var lt = parseFloat(__read_number($(this).find('input.product_line_total')));
        if (lt) { grand += lt; }
    });
    $('span#total_adjustment').text(__number_f(grand));
    $('span#final_total_text').text(__number_f(grand));
    $('input#total_amount').val(grand);
}
