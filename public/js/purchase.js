$(document).ready(function() {
    if ($('input#iraqi_selling_price_adjustment').length > 0) {
        iraqi_selling_price_adjustment = true;
    } else {
        iraqi_selling_price_adjustment = false;
    }

    //Date picker
    $('#transaction_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });

    $('#delivery_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });

    //get suppliers
    $('#supplier_id').select2({
        ajax: {
            url: '/purchases/get_suppliers',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                };
            },
            processResults: function(data) {
                return {
                    results: data,
                };
            },
        },
        minimumInputLength: 1,
        escapeMarkup: function(m) {
            return m;
        },
        templateResult: function(data) {
            if (!data.id) {
                return data.text;
            }
            var html = data.text + ' - ' + data.business_name + ' (' + data.contact_id + ')';
            return html;
        },
        language: {
            noResults: function() {
                var name = $('#supplier_id')
                    .data('select2')
                    .dropdown.$search.val();
                return (
                    '<button type="button" data-name="' +
                    name +
                    '" class="btn btn-link add_new_supplier"><i class="fa fa-plus-circle fa-lg" aria-hidden="true"></i>&nbsp; ' +
                    __translate('add_name_as_new_supplier', { name: name }) +
                    '</button>'
                );
            },
        },
    }).on('select2:select', function (e) {
        var data = e.params.data;
        $('#pay_term_number').val(data.pay_term_number);
        $('#pay_term_type').val(data.pay_term_type);
        $('#advance_balance_text').text(__currency_trans_from_en(data.balance), true);
        $('#advance_balance').val(data.balance);
        set_supplier_address(data);
    });

    //Quick add supplier
    $(document).on('click', '.add_new_supplier', function() {
        $('#supplier_id').select2('close');
        var name = $(this).data('name');
        $('.contact_modal')
            .find('input#name')
            .val(name);
        $('.contact_modal')
            .find('select#contact_type')
            .val('supplier')
            .closest('div.contact_type_div')
            .addClass('hide');
        $('.contact_modal').modal('show');
    });

    $('form#quick_add_contact')
        .submit(function(e) {
            e.preventDefault();
        })
        .validate({
            rules: {
                contact_id: {
                    remote: {
                        url: '/contacts/check-contacts-id',
                        type: 'post',
                        data: {
                            contact_id: function() {
                                return $('#contact_id').val();
                            },
                            hidden_id: function() {
                                if ($('#hidden_id').length) {
                                    return $('#hidden_id').val();
                                } else {
                                    return '';
                                }
                            },
                        },
                    },
                },
            },
            messages: {
                contact_id: {
                    remote: LANG.contact_id_already_exists,
                },
            },
            submitHandler: function(form) {
                __disable_submit_button($(form).find('button[type="submit"]'));
                checkTaxNumberAndSubmitPurchase(form);
            },
        });

    function checkTaxNumberAndSubmitPurchase(form) {
        // Check if tax_number field exists and has a value
        if ($('#tax_number').length && $('#tax_number').val().trim() !== '') {
            $.ajax({
                method: 'POST',
                url: base_path + '/contacts/check-tax-number',
                dataType: 'json',
                data: {
                    contact_id: $('#hidden_id').val(),
                    tax_number: $('#tax_number').val(),
                },
                success: function(result) {
                    if (result.is_tax_number_exists == true) {
                        swal({
                            title: LANG.sure,
                            text: result.msg,
                            icon: 'warning',
                            buttons: true,
                            dangerMode: true,
                        }).then(willContinue => {
                            if (willContinue) {
                                checkMobileAndSubmitPurchase(form);
                            } else {
                                $('#tax_number').select();
                            }
                        });
                    } else {
                        checkMobileAndSubmitPurchase(form);
                    }
                },
            });
        } else {
            // If no tax number, proceed to mobile check
            checkMobileAndSubmitPurchase(form);
        }
    }

    function checkMobileAndSubmitPurchase(form) {
        $.ajax({
            method: 'POST',
            url: base_path + '/check-mobile',
            dataType: 'json',
            data: {
                contact_id: function() {
                    return $('#hidden_id').val();
                },
                mobile_number: function() {
                    return $('#mobile').val();
                },
            },
            success: function(result) {
                if (result.is_mobile_exists == true) {
                    swal({
                        title: LANG.sure,
                        text: result.msg,
                        icon: 'warning',
                        buttons: true,
                        dangerMode: true,
                    }).then(willContinue => {
                        if (willContinue) {
                            submitQuickAddPurchaseContactForm(form);
                        } else {
                            $('#mobile').select();
                        }
                    });
                    
                } else {
                    submitQuickAddPurchaseContactForm(form);
                }
            },
        });
    }

    $('.contact_modal').on('hidden.bs.modal', function() {
        $('form#quick_add_contact')
            .find('button[type="submit"]')
            .removeAttr('disabled');
        $('form#quick_add_contact')[0].reset();
    });

    //Add products
    if ($('#search_product').length > 0) {
        $('#search_product')
            .autocomplete({
                source: function(request, response) {
                    $.getJSON(
                        '/purchases/get_products',
                        { location_id: $('#location_id').val(), term: request.term },
                        response
                    );
                },
                minLength: 2,
                response: function(event, ui) {
                    if (ui.content.length == 1) {
                        ui.item = ui.content[0];
                        $(this)
                            .data('ui-autocomplete')
                            ._trigger('select', 'autocompleteselect', ui);
                        $(this).autocomplete('close');
                    } else if (ui.content.length == 0) {
                        var term = $(this).data('ui-autocomplete').term;
                        swal({
                            title: LANG.no_products_found,
                            text: __translate('add_name_as_new_product', { term: term }),
                            buttons: [LANG.cancel, LANG.ok],
                        }).then(value => {
                            if (value) {
                                var container = $('.quick_add_product_modal');
                                $.ajax({
                                    url: '/products/quick_add?product_name=' + term,
                                    dataType: 'html',
                                    success: function(result) {
                                        $(container)
                                            .html(result)
                                            .modal('show');
                                    },
                                });
                            }
                        });
                    }
                },
                select: function(event, ui) {
                    $(this).val(null);
                    //PosInnova: open click-based location picker (POS-style) before adding the row.
                    if ($('#pi_purchase_location_picker_modal').length && ui.item.variation_id) {
                        pi_open_purchase_location_picker({
                            product_id: ui.item.product_id,
                            variation_id: ui.item.variation_id,
                            product_name: ui.item.text || ui.item.name || '',
                            row_index: '' //add-new flow
                        });
                    } else {
                        get_purchase_entry_row(ui.item.product_id, ui.item.variation_id);
                    }
                },
            })
            .autocomplete('instance')._renderItem = function(ul, item) {
            return $('<li>')
                .append('<div>' + escapeHtml(item.text) + '</div>')
                .appendTo(ul);
        };
    }

    $(document).on('click', '.remove_purchase_entry_row', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(value => {
            if (value) {
                $(this)
                    .closest('tr')
                    .remove();
                update_table_total();
                update_grand_total();
                update_table_sr_number();
            }
        });
    });

    //On Change of quantity
    $(document).on('change', '.purchase_quantity', function() {
        var row = $(this).closest('tr');
        var quantity = __read_number($(this), true);
        var purchase_before_tax = __read_number(row.find('input.purchase_unit_cost'), true);
        var purchase_after_tax = __read_number(
            row.find('input.purchase_unit_cost_after_tax'),
            true
        );

        //Calculate sub totals
        var sub_total_before_tax = quantity * purchase_before_tax;
        var sub_total_after_tax = quantity * purchase_after_tax;

        row.find('.row_subtotal_before_tax').text(
            __currency_trans_from_en(sub_total_before_tax, false, true)
        );
        __write_number(
            row.find('input.row_subtotal_before_tax_hidden'),
            sub_total_before_tax,
            true
        );

        row.find('.row_subtotal_after_tax').text(
            __currency_trans_from_en(sub_total_after_tax, false, true)
        );
        __write_number(row.find('input.row_subtotal_after_tax_hidden'), sub_total_after_tax, true);
        if(!row.find('.row_subtotal_after_tax_input').is(':focus')) { __write_number(row.find('.row_subtotal_after_tax_input'), sub_total_after_tax, true); }

        update_table_total();
        update_grand_total();
    });

    $(document).on('change', '.purchase_unit_cost_without_discount', function() {
        var purchase_before_discount = __read_number($(this), true);

        var row = $(this).closest('tr');
        var discount_percent = __read_number(row.find('input.inline_discounts'), true);
        var quantity = __read_number(row.find('input.purchase_quantity'), true);

        //Calculations.
        var purchase_before_tax =
            parseFloat(purchase_before_discount) -
            __calculate_amount('percentage', discount_percent, purchase_before_discount);

        __write_number(row.find('input.purchase_unit_cost'), purchase_before_tax, true, 6);

        var sub_total_before_tax = quantity * purchase_before_tax;

        //Tax
        var tax_rate = parseFloat(
            row
                .find('select.purchase_line_tax_id')
                .find(':selected')
                .data('tax_amount')
        );
        var tax = __calculate_amount('percentage', tax_rate, purchase_before_tax);

        var purchase_after_tax = purchase_before_tax + tax;
        var sub_total_after_tax = quantity * purchase_after_tax;

        row.find('.row_subtotal_before_tax').text(
            __currency_trans_from_en(sub_total_before_tax, false, true)
        );
        __write_number(
            row.find('input.row_subtotal_before_tax_hidden'),
            sub_total_before_tax,
            true
        );

        __write_number(row.find('input.purchase_unit_cost_after_tax'), purchase_after_tax, true, 6);
        row.find('.row_subtotal_after_tax').text(
            __currency_trans_from_en(sub_total_after_tax, false, true)
        );
        __write_number(row.find('input.row_subtotal_after_tax_hidden'), sub_total_after_tax, true);
        if(!row.find('.row_subtotal_after_tax_input').is(':focus')) { __write_number(row.find('.row_subtotal_after_tax_input'), sub_total_after_tax, true); }

        row.find('.purchase_product_unit_tax_text').text(
            __currency_trans_from_en(tax, false, true)
        );
        __write_number(row.find('input.purchase_product_unit_tax'), tax, true);

        update_inline_profit_percentage(row);
        update_table_total();
        update_grand_total();
    });

    $(document).on('change', '.inline_discounts', function() {
        var row = $(this).closest('tr');

        var discount_percent = __read_number($(this), true);

        var quantity = __read_number(row.find('input.purchase_quantity'), true);
        var purchase_before_discount = __read_number(
            row.find('input.purchase_unit_cost_without_discount'),
            true
        );

        //Calculations.
        var purchase_before_tax =
            parseFloat(purchase_before_discount) -
            __calculate_amount('percentage', discount_percent, purchase_before_discount);

        __write_number(row.find('input.purchase_unit_cost'), purchase_before_tax, true, 6);

        var sub_total_before_tax = quantity * purchase_before_tax;

        //Tax
        var tax_rate = parseFloat(
            row
                .find('select.purchase_line_tax_id')
                .find(':selected')
                .data('tax_amount')
        );
        var tax = __calculate_amount('percentage', tax_rate, purchase_before_tax);

        var purchase_after_tax = purchase_before_tax + tax;
        var sub_total_after_tax = quantity * purchase_after_tax;

        row.find('.row_subtotal_before_tax').text(
            __currency_trans_from_en(sub_total_before_tax, false, true)
        );
        __write_number(
            row.find('input.row_subtotal_before_tax_hidden'),
            sub_total_before_tax,
            true
        );

        __write_number(row.find('input.purchase_unit_cost_after_tax'), purchase_after_tax, true, 6);
        row.find('.row_subtotal_after_tax').text(
            __currency_trans_from_en(sub_total_after_tax, false, true)
        );
        __write_number(row.find('input.row_subtotal_after_tax_hidden'), sub_total_after_tax, true);
        if(!row.find('.row_subtotal_after_tax_input').is(':focus')) { __write_number(row.find('.row_subtotal_after_tax_input'), sub_total_after_tax, true); }
        row.find('.purchase_product_unit_tax_text').text(
            __currency_trans_from_en(tax, false, true)
        );
        __write_number(row.find('input.purchase_product_unit_tax'), tax, true);

        update_inline_profit_percentage(row);
        update_table_total();
        update_grand_total();
    });

    $(document).on('change', '.purchase_unit_cost', function() {
        var row = $(this).closest('tr');
        var quantity = __read_number(row.find('input.purchase_quantity'), true);
        var purchase_before_tax = __read_number($(this), true);

        var sub_total_before_tax = quantity * purchase_before_tax;

        //Update unit cost price before discount
        var discount_percent = __read_number(row.find('input.inline_discounts'), true);
        var purchase_before_discount = __get_principle(purchase_before_tax, discount_percent, true);
        __write_number(
            row.find('input.purchase_unit_cost_without_discount'),
            purchase_before_discount,
            true
        );

        //Tax
        var tax_rate = parseFloat(
            row
                .find('select.purchase_line_tax_id')
                .find(':selected')
                .data('tax_amount')
        );
        var tax = __calculate_amount('percentage', tax_rate, purchase_before_tax);

        var purchase_after_tax = purchase_before_tax + tax;
        var sub_total_after_tax = quantity * purchase_after_tax;

        row.find('.row_subtotal_before_tax').text(
            __currency_trans_from_en(sub_total_before_tax, false, true)
        );
        __write_number(
            row.find('input.row_subtotal_before_tax_hidden'),
            sub_total_before_tax,
            true
        );

        row.find('.purchase_product_unit_tax_text').text(
            __currency_trans_from_en(tax, false, true)
        );
        __write_number(row.find('input.purchase_product_unit_tax'), tax, true);

        //row.find('.purchase_product_unit_tax_text').text( tax );
        __write_number(row.find('input.purchase_unit_cost_after_tax'), purchase_after_tax, true, 6);
        row.find('.row_subtotal_after_tax').text(
            __currency_trans_from_en(sub_total_after_tax, false, true)
        );
        __write_number(row.find('input.row_subtotal_after_tax_hidden'), sub_total_after_tax, true);
        if(!row.find('.row_subtotal_after_tax_input').is(':focus')) { __write_number(row.find('.row_subtotal_after_tax_input'), sub_total_after_tax, true); }

        update_inline_profit_percentage(row);
        update_table_total();
        update_grand_total();
    });

    $(document).on('change', 'select.purchase_line_tax_id', function() {
        var row = $(this).closest('tr');
        var purchase_before_tax = __read_number(row.find('.purchase_unit_cost'), true);
        var quantity = __read_number(row.find('input.purchase_quantity'), true);

        //Tax
        var tax_rate = parseFloat(
            $(this)
                .find(':selected')
                .data('tax_amount')
        );
        var tax = __calculate_amount('percentage', tax_rate, purchase_before_tax);

        //Purchase price
        var purchase_after_tax = purchase_before_tax + tax;
        var sub_total_after_tax = quantity * purchase_after_tax;

        row.find('.purchase_product_unit_tax_text').text(
            __currency_trans_from_en(tax, false, true)
        );
        __write_number(row.find('input.purchase_product_unit_tax'), tax, true);

        __write_number(row.find('input.purchase_unit_cost_after_tax'), purchase_after_tax, true, 6);

        row.find('.row_subtotal_after_tax').text(
            __currency_trans_from_en(sub_total_after_tax, false, true)
        );
        __write_number(row.find('input.row_subtotal_after_tax_hidden'), sub_total_after_tax, true);
        if(!row.find('.row_subtotal_after_tax_input').is(':focus')) { __write_number(row.find('.row_subtotal_after_tax_input'), sub_total_after_tax, true); }

        update_table_total();
        update_grand_total();
    });

    $(document).on('keyup change', '.row_subtotal_after_tax_input', function() {
        var row = $(this).closest('tr');
        var new_subtotal = __read_number($(this), true);
        var quantity = __read_number(row.find('input.purchase_quantity'), true);

        if (quantity != 0) {
            var new_unit_cost_after_tax = new_subtotal / quantity;
            var tax_rate = parseFloat(
                row
                    .find('select.purchase_line_tax_id')
                    .find(':selected')
                    .data('tax_amount')
            );
            if (isNaN(tax_rate)) {
                tax_rate = 0;
            }
            var new_unit_cost_before_tax = new_unit_cost_after_tax / (1 + (tax_rate / 100));

            // Set the new purchase unit cost (before tax) with 6 decimals to prevent rounding errors
            __write_number(row.find('.purchase_unit_cost'), new_unit_cost_before_tax, true, 6);

            // Back-calculate the pp_without_discount based on inline discount
            var discount_percent = __read_number(row.find('.inline_discounts'), true);
            var new_pp_without_discount = new_unit_cost_before_tax;
            if (discount_percent > 0 && discount_percent < 100) {
                new_pp_without_discount = new_unit_cost_before_tax / (1 - (discount_percent / 100));
            } else if (discount_percent >= 100) {
                new_pp_without_discount = 0;
            }
            
            __write_number(row.find('.purchase_unit_cost_without_discount'), new_pp_without_discount, true, 6);
            
            // Trigger the normal calculation cascade
            row.find('.purchase_unit_cost_without_discount').change();
        }
    });

    $(document).on('change', '.purchase_unit_cost_after_tax', function() {
        var row = $(this).closest('tr');
        var purchase_after_tax = __read_number($(this), true);
        var quantity = __read_number(row.find('input.purchase_quantity'), true);

        var sub_total_after_tax = purchase_after_tax * quantity;

        //Tax
        var tax_rate = parseFloat(
            row
                .find('select.purchase_line_tax_id')
                .find(':selected')
                .data('tax_amount')
        );
        var purchase_before_tax = __get_principle(purchase_after_tax, tax_rate);
        var sub_total_before_tax = quantity * purchase_before_tax;
        var tax = __calculate_amount('percentage', tax_rate, purchase_before_tax);

        //Update unit cost price before discount
        var discount_percent = __read_number(row.find('input.inline_discounts'), true);
        var purchase_before_discount = __get_principle(purchase_before_tax, discount_percent, true);
        __write_number(
            row.find('input.purchase_unit_cost_without_discount'),
            purchase_before_discount,
            true
        );

        row.find('.row_subtotal_after_tax').text(
            __currency_trans_from_en(sub_total_after_tax, false, true)
        );
        __write_number(row.find('input.row_subtotal_after_tax_hidden'), sub_total_after_tax, true);
        if(!row.find('.row_subtotal_after_tax_input').is(':focus')) { __write_number(row.find('.row_subtotal_after_tax_input'), sub_total_after_tax, true); }

        __write_number(row.find('.purchase_unit_cost'), purchase_before_tax, true, 6);

        row.find('.row_subtotal_before_tax').text(
            __currency_trans_from_en(sub_total_before_tax, false, true)
        );
        __write_number(
            row.find('input.row_subtotal_before_tax_hidden'),
            sub_total_before_tax,
            true
        );

        row.find('.purchase_product_unit_tax_text').text(__currency_trans_from_en(tax, true, true));
        __write_number(row.find('input.purchase_product_unit_tax'), tax);

        update_table_total();
        update_grand_total();
    });

    $('#tax_id, #discount_type, #discount_amount, input#shipping_charges, \
        #additional_expense_value_1, #additional_expense_value_2, \
        #additional_expense_value_3, #additional_expense_value_4').change(function() {
        update_grand_total();
    });

    //Purchase table
    purchase_table = $('#purchase_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader:false,
        scrollY: "75vh",
        scrollX:        true,
        scrollCollapse: true,
        ajax: {
            url: '/purchases',
            data: function(d) {
                if ($('#purchase_list_filter_location_id').length) {
                    d.location_id = $('#purchase_list_filter_location_id').val();
                }
                if ($('#purchase_list_filter_supplier_id').length) {
                    d.supplier_id = $('#purchase_list_filter_supplier_id').val();
                }
                if ($('#purchase_list_filter_payment_status').length) {
                    d.payment_status = $('#purchase_list_filter_payment_status').val();
                }
                if ($('#purchase_list_filter_status').length) {
                    d.status = $('#purchase_list_filter_status').val();
                }

                var start = '';
                var end = '';
                if ($('#purchase_list_filter_date_range').val()) {
                    start = $('input#purchase_list_filter_date_range')
                        .data('daterangepicker')
                        .startDate.format('YYYY-MM-DD');
                    end = $('input#purchase_list_filter_date_range')
                        .data('daterangepicker')
                        .endDate.format('YYYY-MM-DD');
                }
                d.start_date = start;
                d.end_date = end;

                d = __datatable_ajax_callback(d);
            },
        },
        aaSorting: [[1, 'desc']],
        columns: [
            { data: 'action', name: 'action', orderable: false, searchable: false },
            { data: 'transaction_date', name: 'transaction_date' },
            { data: 'ref_no', name: 'ref_no' },
            { data: 'location_name', name: 'BS.name' },
            { data: 'name', name: 'contacts.name' },
            { data: 'status', name: 'status' },
            { data: 'payment_status', name: 'payment_status' },
            { data: 'final_total', name: 'final_total' },
            { data: 'payment_due', name: 'payment_due', orderable: false, searchable: false },

            { data: 'custom_field_1', name: 'transactions.custom_field_1', visible: typeof customFieldVisibility !== 'undefined' ? customFieldVisibility.custom_field_1 : false },
            { data: 'custom_field_2', name: 'transactions.custom_field_2', visible: typeof customFieldVisibility !== 'undefined' ? customFieldVisibility.custom_field_2 : false },
            { data: 'custom_field_3', name: 'transactions.custom_field_3', visible: typeof customFieldVisibility !== 'undefined' ? customFieldVisibility.custom_field_3 : false },
            { data: 'custom_field_4', name: 'transactions.custom_field_4', visible: typeof customFieldVisibility !== 'undefined' ? customFieldVisibility.custom_field_4 : false },

            
            { data: 'added_by', name: 'u.first_name' },
        ],
        fnDrawCallback: function(oSettings) {
            __currency_convert_recursively($('#purchase_table'));
        },
        "footerCallback": function ( row, data, start, end, display ) {
            var total_purchase = 0;
            var total_due = 0;
            var total_purchase_return_due = 0;
            for (var r in data){
                total_purchase += $(data[r].final_total).data('orig-value') ? 
                parseFloat($(data[r].final_total).data('orig-value')) : 0;
                var payment_due_obj = $('<div>' + data[r].payment_due + '</div>');
                total_due += payment_due_obj.find('.payment_due').data('orig-value') ? 
                parseFloat(payment_due_obj.find('.payment_due').data('orig-value')) : 0;

                total_purchase_return_due += payment_due_obj.find('.purchase_return').data('orig-value') ? 
                parseFloat(payment_due_obj.find('.purchase_return').data('orig-value')) : 0;
            }

            $('.footer_purchase_total').html(__currency_trans_from_en(total_purchase));
            $('.footer_total_due').html(__currency_trans_from_en(total_due));
            $('.footer_total_purchase_return_due').html(__currency_trans_from_en(total_purchase_return_due));
            $('.footer_status_count').html(__count_status(data, 'status'));
            $('.footer_payment_status_count').html(__count_status(data, 'payment_status'));
        },
        createdRow: function(row, data, dataIndex) {
            $(row)
                .find('td:eq(5)')
                .attr('class', 'clickable_td');
        },
    });

    $(document).on(
        'change',
        '#purchase_list_filter_location_id, \
                    #purchase_list_filter_supplier_id, #purchase_list_filter_payment_status,\
                     #purchase_list_filter_status',
        function() {
            purchase_table.ajax.reload();
        }
    );

    update_table_sr_number();

    $(document).on('change', '.mfg_date', function() {
        var this_date = $(this).val();
        var this_moment = moment(this_date, moment_date_format);
        var expiry_period = parseFloat(
            $(this)
                .closest('td')
                .find('.row_product_expiry')
                .val()
        );
        var expiry_period_type = $(this)
            .closest('td')
            .find('.row_product_expiry_type')
            .val();
        if (this_date) {
            if (expiry_period && expiry_period_type) {
                exp_date = this_moment
                    .add(expiry_period, expiry_period_type)
                    .format(moment_date_format);
                $(this)
                    .closest('td')
                    .find('.exp_date')
                    .datepicker('update', exp_date);
            } else {
                $(this)
                    .closest('td')
                    .find('.exp_date')
                    .datepicker('update', '');
            }
        } else {
            $(this)
                .closest('td')
                .find('.exp_date')
                .datepicker('update', '');
        }
    });

    $('#purchase_entry_table tbody')
        .find('.expiry_datepicker')
        .each(function() {
            $(this).datepicker({
                autoclose: true,
                format: datepicker_date_format,
            });
        });

    $(document).on('change', '.profit_percent', function() {
        var row = $(this).closest('tr');
        var profit_percent = __read_number($(this), true);

        var purchase_unit_cost = __read_number(row.find('input.purchase_unit_cost_after_tax'), true);
        var default_sell_price =
            parseFloat(purchase_unit_cost) +
            __calculate_amount('percentage', profit_percent, purchase_unit_cost);
        var exchange_rate = $('input#exchange_rate').val();
        __write_number(
            row.find('input.default_sell_price'),
            default_sell_price * exchange_rate,
            true
        );
    });

    $(document).on('change', '.default_sell_price', function() {
        var row = $(this).closest('tr');
        update_inline_profit_percentage(row);
    });

    $(document).on('click', 'a.delete-purchase', function(e) {
        e.preventDefault();
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                var href = $(this).attr('href');
                $.ajax({
                    method: 'DELETE',
                    url: href,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success == true) {
                            toastr.success(result.msg);
                            purchase_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });

    $('table#purchase_entry_table').on('change', 'select.sub_unit', function() {
        var tr = $(this).closest('tr');
        var base_unit_cost = tr.find('input.base_unit_cost').val();
        var base_unit_selling_price = tr.find('input.base_unit_selling_price').val();

        var multiplier = parseFloat(
            $(this)
                .find(':selected')
                .data('multiplier')
        );

        var unit_sp = base_unit_selling_price * multiplier;
        var unit_cost = base_unit_cost * multiplier;

        var sp_element = tr.find('input.default_sell_price');
        __write_number(sp_element, unit_sp);

        var cp_element = tr.find('input.purchase_unit_cost_without_discount');
        __write_number(cp_element, unit_cost);
        cp_element.change();
        update_purchase_per_base_hint(tr);
    });

    //PosInnova: live "per base unit" hint next to purchase cost.
    $('table#purchase_entry_table').on('keyup change', 'input.purchase_unit_cost_without_discount', function() {
        update_purchase_per_base_hint($(this).closest('tr'));
    });
    toggle_search();
});

function get_purchase_entry_row(product_id, variation_id, target_location_id) {
    if (product_id) {
        var row_count = $('#row_count').val();
        var location_id = $('#location_id').val();
        var supplier_id = $('#supplier_id').val();
        var data = { 
            product_id: product_id, 
            row_count: row_count, 
            variation_id: variation_id,
            location_id: location_id,
            supplier_id: supplier_id
        };

        //PosInnova: per-line destination location
        if (target_location_id) {
            data.target_location_id = target_location_id;
        }

        if ($('#is_purchase_order').length) {
            data.is_purchase_order = true;
        }
        $.ajax({
            method: 'POST',
            url: '/purchases/get_purchase_entry_row',
            dataType: 'html',
            data: data,
            success: function(result) {
                append_purchase_lines(result, row_count);
            },
        });
    }
}

//PosInnova: opens the click-based location picker for both add-new and edit-existing flows.
//`opts` = { product_id, variation_id, product_name, row_index }
function pi_open_purchase_location_picker(opts) {
    var $modal = $('#pi_purchase_location_picker_modal');
    if (!$modal.length || !opts.variation_id) return;

    $('#pi_pending_product_id').val(opts.product_id || '');
    $('#pi_pending_variation_id').val(opts.variation_id);
    $('#pi_editing_row_index').val(opts.row_index === undefined ? '' : opts.row_index);
    $('#pi_purchase_loc_product_name').text(opts.product_name || '');

    var $list = $('#pi_purchase_loc_list');
    $list.html('<div class="text-center text-muted" style="padding:14px;"><i class="fa fa-spinner fa-spin"></i> ...</div>');
    $modal.modal('show');

    $.getJSON('/sells/pos/get-variation-locations/' + opts.variation_id, function(resp) {
        $list.empty();
        if (!resp || !resp.success || !resp.locations || !resp.locations.length) {
            $list.append('<div class="alert alert-warning" style="margin-bottom:0;">'
                + '<i class="fa fa-exclamation-triangle"></i> No locations available.'
                + '</div>');
            return;
        }
        if (opts.product_name === '' || !opts.product_name) {
            $('#pi_purchase_loc_product_name').text(resp.product_name || '');
        }
        var unit = resp.unit || '';
        resp.locations.forEach(function(l) {
            var qtyLabelClass = l.qty_available > 0 ? 'success' : 'default';
            var $btn = $('<button type="button" class="list-group-item"></button>')
                .css('cursor', 'pointer')
                .html('<strong>' + l.name + '</strong> '
                    + '<span class="pull-right label label-' + qtyLabelClass + '">'
                    + 'In stock: ' + l.formatted + ' ' + unit + '</span>');
            $btn.on('click', function() {
                $modal.modal('hide');
                pi_apply_purchase_location_choice(l.location_id, l.name);
            });
            $list.append($btn);
        });
    }).fail(function() {
        $list.html('<div class="alert alert-danger" style="margin-bottom:0;">'
            + '<i class="fa fa-times-circle"></i> Failed to load locations.'
            + '</div>');
    });
}

//PosInnova: applies the chosen destination location either to a brand-new row (fetch + append)
//or to an existing row's hidden input + visible badge.
function pi_apply_purchase_location_choice(target_location_id, target_location_name) {
    if (!target_location_id) return;
    var editing_row_index = $('#pi_editing_row_index').val();
    if (editing_row_index !== '' && editing_row_index !== null) {
        var $row = $('#purchase_entry_table tbody tr').eq(parseInt(editing_row_index, 10));
        if ($row.length) {
            $row.find('input.pi-row-location-id').val(target_location_id);
            $row.attr('data-target_location_id', target_location_id);
            $row.find('.pi-row-location-name').text(target_location_name);
        }
        return;
    }
    var product_id = $('#pi_pending_product_id').val();
    var variation_id = $('#pi_pending_variation_id').val();
    if (product_id) {
        get_purchase_entry_row(product_id, variation_id, target_location_id);
    }
}

//PosInnova: clicking the "Edit" link on an existing row opens the picker for that row.
$(document).on('click', '.pi-row-change-location', function() {
    var $row = $(this).closest('tr');
    var rowIndex = $row.index();
    var variation_id = $row.attr('data-variation_id');
    var product_name = $row.find('td').eq(1).clone().children('br,small,span,a').remove().end().text().trim();
    pi_open_purchase_location_picker({
        product_id: '',
        variation_id: variation_id,
        product_name: product_name,
        row_index: rowIndex
    });
});

function append_purchase_lines(data, row_count, trigger_change = false) {
    $(data)
        .find('.purchase_quantity')
        .each(function() {
            row = $(this).closest('tr');

            $('#purchase_entry_table tbody').append(
                update_purchase_entry_row_values(row)
            );
            update_row_price_for_exchange_rate(row);

            update_inline_profit_percentage(row);

            update_table_total();
            update_grand_total();
            update_table_sr_number();

            //Check if multipler is present then multiply it when a new row is added.
            if(__getUnitMultiplier(row) > 1){
                row.find('select.sub_unit').trigger('change');
            }

            if (trigger_change && row.find('.purchase_unit_cost_without_discount').length) {
                row.find('.purchase_unit_cost_without_discount').trigger('change');
            }
        });
    if ($(data).find('.purchase_quantity').length) {
        $('#row_count').val(
            $(data).find('.purchase_quantity').length + parseInt(row_count)
        );
    }
}

function update_purchase_entry_row_values(row) {
    if (typeof row != 'undefined') {
        var quantity = __read_number(row.find('.purchase_quantity'), true);
        var unit_cost_price = __read_number(row.find('.purchase_unit_cost'), true);
        var row_subtotal_before_tax = quantity * unit_cost_price;

        var tax_rate = parseFloat(
            $('option:selected', row.find('.purchase_line_tax_id')).attr('data-tax_amount')
        );

        var unit_product_tax = __calculate_amount('percentage', tax_rate, unit_cost_price);

        var unit_cost_price_after_tax = unit_cost_price + unit_product_tax;
        var row_subtotal_after_tax = quantity * unit_cost_price_after_tax;

        row.find('.row_subtotal_before_tax').text(
            __currency_trans_from_en(row_subtotal_before_tax, false, true)
        );
        __write_number(row.find('.row_subtotal_before_tax_hidden'), row_subtotal_before_tax, true);
        __write_number(row.find('.purchase_product_unit_tax'), unit_product_tax, true);
        row.find('.purchase_product_unit_tax_text').text(
            __currency_trans_from_en(unit_product_tax, false, true)
        );
        row.find('.purchase_unit_cost_after_tax').text(
            __currency_trans_from_en(unit_cost_price_after_tax, true)
        );
        row.find('.row_subtotal_after_tax').text(
            __currency_trans_from_en(row_subtotal_after_tax, false, true)
        );
        __write_number(row.find('.row_subtotal_after_tax_hidden'), row_subtotal_after_tax, true);
        __write_number(row.find('.row_subtotal_after_tax_input'), row_subtotal_after_tax, true);

        row.find('.expiry_datepicker').each(function() {
            $(this).datepicker({
                autoclose: true,
                format: datepicker_date_format,
            });
        });
        return row;
    }
}

function update_row_price_for_exchange_rate(row) {
    var exchange_rate = $('input#exchange_rate').val();

    if (exchange_rate == 1) {
        return true;
    }

    var purchase_unit_cost_without_discount =
        __read_number(row.find('.purchase_unit_cost_without_discount'), true) / exchange_rate;
    __write_number(
        row.find('.purchase_unit_cost_without_discount'),
        purchase_unit_cost_without_discount,
        true,
        6
    );

    var purchase_unit_cost = __read_number(row.find('.purchase_unit_cost'), true) / exchange_rate;
    __write_number(row.find('.purchase_unit_cost'), purchase_unit_cost, true, 6);

    var row_subtotal_before_tax_hidden =
        __read_number(row.find('.row_subtotal_before_tax_hidden'), true) / exchange_rate;
    row.find('.row_subtotal_before_tax').text(
        __currency_trans_from_en(row_subtotal_before_tax_hidden, false, true)
    );
    __write_number(
        row.find('input.row_subtotal_before_tax_hidden'),
        row_subtotal_before_tax_hidden,
        true
    );

    var purchase_product_unit_tax =
        __read_number(row.find('.purchase_product_unit_tax'), true) / exchange_rate;
    __write_number(row.find('input.purchase_product_unit_tax'), purchase_product_unit_tax, true);
    row.find('.purchase_product_unit_tax_text').text(
        __currency_trans_from_en(purchase_product_unit_tax, false, true)
    );

    var purchase_unit_cost_after_tax =
        __read_number(row.find('.purchase_unit_cost_after_tax'), true) / exchange_rate;
    __write_number(
        row.find('input.purchase_unit_cost_after_tax'),
        purchase_unit_cost_after_tax,
        true
    );

    var row_subtotal_after_tax_hidden =
        __read_number(row.find('.row_subtotal_after_tax_hidden'), true) / exchange_rate;
    __write_number(
        row.find('input.row_subtotal_after_tax_hidden'),
        row_subtotal_after_tax_hidden,
        true
    );
    if(!row.find('.row_subtotal_after_tax_input').is(':focus')) { 
        __write_number(row.find('.row_subtotal_after_tax_input'), row_subtotal_after_tax_hidden, true); 
    }
    row.find('.row_subtotal_after_tax').text(
        __currency_trans_from_en(row_subtotal_after_tax_hidden, false, true)
    );
}

function iraqi_dinnar_selling_price_adjustment(row) {
    var default_sell_price = __read_number(row.find('input.default_sell_price'), true);

    //Adjsustment
    var remaining = default_sell_price % 250;
    if (remaining >= 125) {
        default_sell_price += 250 - remaining;
    } else {
        default_sell_price -= remaining;
    }

    __write_number(row.find('input.default_sell_price'), default_sell_price, true);

    update_inline_profit_percentage(row);
}

function update_inline_profit_percentage(row) {
    //Update Profit percentage
    var default_sell_price = __read_number(row.find('input.default_sell_price'), true);
    var exchange_rate = $('input#exchange_rate').val();
    default_sell_price_in_base_currency = default_sell_price / parseFloat(exchange_rate);

    var purchase_after_tax = __read_number(row.find('input.purchase_unit_cost_after_tax'), true);
    var profit_percent = __get_rate(purchase_after_tax, default_sell_price_in_base_currency);
    __write_number(row.find('input.profit_percent'), profit_percent, true);
}

function update_table_total() {
    var total_quantity = 0;
    var total_st_before_tax = 0;
    var total_subtotal = 0;

    $('#purchase_entry_table tbody')
        .find('tr')
        .each(function() {
            total_quantity += __read_number($(this).find('.purchase_quantity'), true);
            total_st_before_tax += __read_number(
                $(this).find('.row_subtotal_before_tax_hidden'),
                true
            );
            total_subtotal += __read_number($(this).find('.row_subtotal_after_tax_hidden'), true);
        });

    $('#total_quantity').text(__number_f(total_quantity, false));
    $('#total_st_before_tax').text(__currency_trans_from_en(total_st_before_tax, true, true));
    __write_number($('input#st_before_tax_input'), total_st_before_tax, true);

    $('#total_subtotal').text(__currency_trans_from_en(total_subtotal, true, true));
    __write_number($('input#total_subtotal_input'), total_subtotal, true);
}

function update_grand_total() {
    var st_before_tax = __read_number($('input#st_before_tax_input'), true);
    var total_subtotal = __read_number($('input#total_subtotal_input'), true);
    //Calculate Discount
    var discount_type = $('select#discount_type').val();
    var discount_amount = __read_number($('input#discount_amount'), true);
    var discount = __calculate_amount(discount_type, discount_amount, total_subtotal);
    $('#discount_calculated_amount').text(__currency_trans_from_en(discount, true, true));

    //Calculate Tax
    var tax_rate = parseFloat($('option:selected', $('#tax_id')).data('tax_amount'));
    var tax = __calculate_amount('percentage', tax_rate, total_subtotal - discount);
    __write_number($('input#tax_amount'), tax);
    $('#tax_calculated_amount').text(__currency_trans_from_en(tax, true, true));

    //Calculate shipping
    var shipping_charges = __read_number($('input#shipping_charges'), true);

    //calculate additional expenses
    var additional_expense_1 = __read_number($('input#additional_expense_value_1'), true);
    var additional_expense_2 = __read_number($('input#additional_expense_value_2'), true);
    var additional_expense_3 = __read_number($('input#additional_expense_value_3'), true);
    var additional_expense_4 = __read_number($('input#additional_expense_value_4'), true);

    //Calculate Final total
    grand_total = total_subtotal - discount + tax + shipping_charges + 
    additional_expense_1 + additional_expense_2 + additional_expense_3 + additional_expense_4;

    __write_number($('input#grand_total_hidden'), grand_total, true);

    //PosInnova: default the payment amount to the purchase total unless the user has manually edited it.
    var $pay = $('input.payment-amount');
    if ($pay.length && !$pay.data('user_edited')) {
        __write_number($pay, grand_total, true);
    }

    var payment = __read_number($pay, true);

    var due = grand_total - payment;

    $('#grand_total').text(__currency_trans_from_en(grand_total, true, true));

    $('#payment_due').text(__currency_trans_from_en(due, true, true));

    //__currency_convert_recursively($(document));
}
$(document).on('change', 'input.payment-amount', function() {
    //PosInnova: mark as user-edited so subsequent total recalculations don't overwrite it.
    $(this).data('user_edited', true);
    var payment = __read_number($(this), true);
    var grand_total = __read_number($('input#grand_total_hidden'), true);
    var bal = grand_total - payment;
    $('#payment_due').text(__currency_trans_from_en(bal, true, true));
});

function update_table_sr_number() {
    var sr_number = 1;
    $('table#purchase_entry_table tbody')
        .find('.sr_number')
        .each(function() {
            $(this).text(sr_number);
            sr_number++;
        });
}

$(document).on('click', 'button#submit_purchase_form', function(e) {
    e.preventDefault();

    //Check if product is present or not.
    if ($('table#purchase_entry_table tbody tr').length <= 0) {
        toastr.warning(LANG.no_products_added);
        $('input#search_product').select();
        return false;
    }

    $('form#add_purchase_form').validate({
        rules: {
            ref_no: {
                remote: {
                    url: '/purchases/check_ref_number',
                    type: 'post',
                    data: {
                        ref_no: function() {
                            return $('#ref_no').val();
                        },
                        contact_id: function() {
                            return $('#supplier_id').val();
                        },
                        purchase_id: function() {
                            if ($('#purchase_id').length > 0) {
                                return $('#purchase_id').val();
                            } else {
                                return '';
                            }
                        },
                    },
                },
            },
        },
        messages: {
            ref_no: {
                remote: LANG.ref_no_already_exists,
            },
        },
    });
    var payment_types_dropdown = $('.payment_types_dropdown')
    var payment_type = payment_types_dropdown.val();
    var payment_row = payment_types_dropdown.closest('.payment_row');
    amount_element = payment_row.find('.payment-amount');
    account_dropdown = payment_row.find('.account-dropdown');
    if (payment_type == 'advance') {
        max_value = $('#advance_balance').val();
        msg = $('#advance_balance').data('error-msg');
        amount_element.rules('add', {
            'max-value': max_value,
            messages: {
                'max-value': msg,
            },
        });
        if (account_dropdown) {
            account_dropdown.prop('disabled', true);
        }
        
    } else {
        amount_element.rules("remove", "max-value");
        if (account_dropdown) {
            account_dropdown.prop('disabled', false); 
        }    
    }

    if ($('.enable_cash_denomination_for_payment_methods').length) {
        var payment_row = $('.enable_cash_denomination_for_payment_methods').closest('.payment_row');
        var is_valid = true;
        var payment_type = payment_row.find('.payment_types_dropdown').val();
        var denomination_for_payment_types = JSON.parse($('.enable_cash_denomination_for_payment_methods').val());
        if (denomination_for_payment_types.includes(payment_type) && payment_row.find('.is_strict').length && payment_row.find('.is_strict').val() === '1' ) {
            var payment_amount = __read_number(payment_row.find('.payment-amount'));
            var total_denomination = payment_row.find('input.denomination_total_amount').val();
            if (payment_amount != total_denomination ) {
                is_valid = false;
            }
        }

        if (!is_valid) {
            payment_row.find('.cash_denomination_error').removeClass('hide');
            toastr.error(payment_row.find('.cash_denomination_error').text());
            e.preventDefault();
            return false;
        } else {
            payment_row.find('.cash_denomination_error').addClass('hide');
        }
    }

    if ($('form#add_purchase_form').valid()) {
        var $purchase_btn = $(this);
        var save_purchase = function () {
            $purchase_btn.attr('disabled', true);
            $('form#add_purchase_form').submit();
        };
        // PosInnova "check before recording", once the checks above passed
        if (window.PosConfirm && PosConfirm.enabled('purchase')) {
            var rows = [];
            var supplier = $.trim($('#supplier_id option:selected').text());
            if (supplier) { rows.push([PosConfirm.label('supplier'), supplier]); }
            var ref = $.trim($('#ref_no').val() || '');
            if (ref) { rows.push([PosConfirm.label('ref'), ref]); }
            var status = $.trim($('#add_purchase_form select[name="status"] option:selected').text());
            if (status) { rows.push([PosConfirm.label('status'), status]); }
            rows.push([PosConfirm.label('items'), String($('#purchase_entry_table tbody tr').length)]);
            var $pay = $('.payment_row').filter(':visible').first();
            if ($pay.length) {
                rows.push([PosConfirm.label('paid'), __currency_trans_from_en(__read_number($pay.find('.payment-amount')) || 0, true)]);
                var method = $.trim($pay.find('.payment_types_dropdown option:selected').text());
                if (method) { rows.push([PosConfirm.label('method'), method]); }
            }
            if ($('#payment_due').length) { rows.push([PosConfirm.label('due'), $.trim($('#payment_due').text())]); }
            PosConfirm.ask({ kind: 'purchase', amount: __read_number($('#grand_total_hidden')) || 0, rows: rows }, save_purchase);
        } else {
            save_purchase();
        }
    }
});

function toggle_search() {
    if ($('#location_id').val()) {
        $('#search_product').removeAttr('disabled');
        $('#search_product').focus();
    } else {
        $('#search_product').attr('disabled', true);
    }
}

$(document).on('change', '#location_id', function() {
    get_purchase_requisitions();
    toggle_search();
    $('#purchase_entry_table tbody').html('');
    update_table_total();
    update_grand_total();
    update_table_sr_number();
});

$(document).on('shown.bs.modal', '.quick_add_product_modal', function(){
    var selected_location = $('#location_id').val();
    if (selected_location) {
        $('.quick_add_product_modal').find('#product_locations').val([selected_location]).trigger("change");
    }
});

function set_supplier_address(data) {
    var address = [];
    if (data.supplier_business_name) {
        address.push(data.supplier_business_name);
    }
    if (data.business_name) {
        address.push(data.business_name);
    }
    if (data.name) {
        address.push('<br>' + data.name);
    }
    if (data.text) {
        address.push('<br>' + data.text);
    }
    if (data.address_line_1) {
        address.push('<br>' + data.address_line_1);
    }
    if (data.address_line_2) {
        address.push('<br>' + data.address_line_2);
    }
    if (data.city) {
        address.push('<br>' + data.city);
    }
    if (data.state) {
        address.push(data.state);
    }
    if (data.country) {
        address.push(data.country);
    }
    if (data.zip_code) {
        address.push('<br>' + data.zip_code);
    }
    var supplier_address = address.join(', ');
    $('#supplier_address_div').html(supplier_address);
}

$(document).on('change', '#supplier_id', function(){
    if ($('#purchase_order_ids').length) {
        contact_id = $(this).val();
        $.ajax({
            url: '/get-purchase-orders/' + contact_id,
            dataType: 'json',
            success: function(data) {
                $('#purchase_order_ids').select2('destroy').empty().select2({data: data});
                $('#purchase_entry_table tbody').find('tr').each( function(){
                    if (typeof($(this).data('purchase_order_id')) !== 'undefined') {
                        $(this).remove();
                    }
                });
            },
        });
    }
});

$("#purchase_order_ids").on("select2:select", function (e) {
    var purchase_order_id = e.params.data.id;
    var row_count = $('#row_count').val();
    $.ajax({
        url: '/get-purchase-order-lines/' + purchase_order_id + '?row_count=' + row_count,
        dataType: 'json',
        success: function(data) {
            set_po_values(data.po);
            append_purchase_lines(data.html, row_count);
        },
    });

});

$("#purchase_order_ids").on("select2:unselect", function (e) {
    var purchase_order_id = e.params.data.id;
    $('#purchase_entry_table tbody').find('tr').each( function(){
        if (typeof($(this).data('purchase_order_id')) !== 'undefined' 
            && $(this).data('purchase_order_id') == purchase_order_id) {
            $(this).remove();
        }
    });
});

function set_po_values(po) {
    $('#shipping_details').val(po.shipping_details);
    $('#shipping_charges').val( __number_f(po.shipping_charges));
    if ($('#shipping_custom_field_1').length) {
        $('#shipping_custom_field_1').val(po.shipping_custom_field_1);
    }
    if ($('#shipping_custom_field_2').length) {
        $('#shipping_custom_field_2').val(po.shipping_custom_field_2);
    }
    if ($('#shipping_custom_field_3').length) {
        $('#shipping_custom_field_3').val(po.shipping_custom_field_3);
    }
    if ($('#shipping_custom_field_4').length) {
        $('#shipping_custom_field_4').val(po.shipping_custom_field_4);
    }
    if ($('#shipping_custom_field_5').length) {
        $('#shipping_custom_field_5').val(po.shipping_custom_field_5);
    }

    $('#additional_expense_key_1').val(po.additional_expense_key_1);
    $('#additional_expense_key_2').val(po.additional_expense_key_2);
    $('#additional_expense_key_3').val(po.additional_expense_key_3);
    $('#additional_expense_key_4').val(po.additional_expense_key_4);

    $('#additional_expense_value_1').val( __number_f(po.additional_expense_value_1));
    $('#additional_expense_value_2').val( __number_f(po.additional_expense_value_2));
    $('#additional_expense_value_3').val( __number_f(po.additional_expense_value_3));
    $('#additional_expense_value_4').val( __number_f(po.additional_expense_value_4));
}

if ($("div#import_product_dz").length) {
    $("div#import_product_dz").dropzone({
        url: base_path + '/import-purchase-products',
        paramName: 'file',
        autoProcessQueue: false,
        addRemoveLinks: true,
        uploadMultiple: false,
        maxFiles:1,
        init: function() {
            this.on("addedfile", function(file) {
                if ($('#location_id').val() == '') {
                    this.removeFile(file);
                    toastr.error('select location first');
                }
            });
            this.on("maxfilesexceeded", function(file) {
                this.removeAllFiles();
                this.addFile(file);
            });
            this.on("sending", function(file, xhr, formData){
                formData.append("location_id", $('#location_id').val());
                formData.append("row_count", $('#row_count').val());
            });
        },   
        acceptedFiles: '.csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(file, response) {
            if (response.success) {
                toastr.success(response.msg);
                var row_count = $('#row_count').val();
                append_purchase_lines(response.html, row_count, true);

                this.removeAllFiles();

                $('#import_purchase_products_modal').modal('hide');
            } else {
                toastr.error(response.msg);
            }
        },
    });
}

$(document).on('click', '#import_purchase_products', function(){
    var productDz = Dropzone.forElement("#import_product_dz");
    productDz.processQueue();
})

function submitQuickAddPurchaseContactForm(form) {
    var data = $(form).serialize();
    $.ajax({
        method: 'POST',
        url: $(form).attr('action'),
        dataType: 'json',
        data: data,
        beforeSend: function(xhr) {
            __disable_submit_button($(form).find('button[type="submit"]'));
        },
        success: function(result) {
            if (result.success == true) {
                var name = result.data.name;

                if (result.data.supplier_business_name) {
                    name += result.data.supplier_business_name;
                }
                $('select#supplier_id').append(
                    $('<option>', { value: result.data.id, text: name })
                );
                $('select#supplier_id')
                    .val(result.data.id)
                    .trigger('change');
                $('div.contact_modal').modal('hide');
                set_supplier_address(result.data);
                toastr.success(result.msg);
            } else {
                toastr.error(result.msg);
            }
        },
    });
}

function get_purchase_requisitions() {
    var location_id = $('#location_id').val();
    if ($('#purchase_requisition_ids').length) {
        if (location_id !== '') {
            $.ajax({
                url: '/get-purchase-requisitions/' + location_id,
                dataType: 'json',
                success: function(data) {
                    $('#purchase_requisition_ids').select2('destroy').empty().select2({data: data});
                    $('#purchase_entry_table tbody').find('tr').each( function(){
                        if (typeof($(this).data('purchase_requisition_id')) !== 'undefined') {
                            $(this).remove();
                        }
                    });
                },
            });
        }
    }
}

$("#purchase_requisition_ids").on("select2:select", function (e) {
    var purchase_requisition_id = e.params.data.id;
    var row_count = $('#row_count').val();
    $.ajax({
        url: '/get-purchase-requisition-lines/' + purchase_requisition_id + '?row_count=' + row_count,
        dataType: 'json',
        success: function(data) {
            append_purchase_lines(data.html, row_count);
        },
    });

});

$("#purchase_requisition_ids").on("select2:unselect", function (e) {
    var purchase_requisition_id = e.params.data.id;
    $('#purchase_entry_table tbody').find('tr').each( function(){
        if (typeof($(this).data('purchase_requisition_id')) !== 'undefined' 
            && $(this).data('purchase_requisition_id') == purchase_requisition_id) {
            $(this).remove();
        }
    });
});



//PosInnova: update per-base-unit cost hint on a purchase row.
function update_purchase_per_base_hint(tr) {
    var multiplier = 1;
    var $sel = tr.find('select.sub_unit');
    if ($sel.length) {
        var m = parseFloat($sel.find('option:selected').data('multiplier'));
        if (!isNaN(m) && m > 0) multiplier = m;
    }
    var unit_cost = __read_number(tr.find('input.purchase_unit_cost_without_discount'), true);
    var per_base = multiplier > 0 ? unit_cost / multiplier : unit_cost;
    tr.find('.unit_price_per_base').text(__number_f(per_base, true));
}

// Update Per-Unit Prices toggle button color dynamically
$(document).on('change', '.per_unit_prices_wrapper input', function() {
    var wrapper = $(this).closest('.per_unit_prices_wrapper');
    var has_val = false;
    wrapper.find('input').each(function() {
        if ($(this).val() !== '') has_val = true;
    });
    var btn = wrapper.find('.toggle_per_unit_prices');
    if (has_val) {
        btn.removeClass('btn-info').addClass('btn-success');
        btn.find('.configured_badge').html('<i class="fa fa-check-circle" title="Prices Configured"></i>');
    } else {
        btn.removeClass('btn-success').addClass('btn-info');
        btn.find('.configured_badge').html('');
    }
});
