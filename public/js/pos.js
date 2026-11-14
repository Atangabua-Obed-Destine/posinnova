var global_brand_id = null;
var global_p_category_id = null;
var global_is_clear_local_storage = false;
$(document).ready(function() {
    customer_set = false;
    //Prevent enter key function except texarea
    $('form').on('keyup keypress', function(e) {
        var keyCode = e.keyCode || e.which;
        if (keyCode === 13 && e.target.tagName != 'TEXTAREA') {
            e.preventDefault();
            return false;
        }
    });

    //For edit pos form
    if ($('form#edit_pos_sell_form').length > 0) {
        pos_total_row();
        pos_form_obj = $('form#edit_pos_sell_form');
    } else {
        pos_form_obj = $('form#add_pos_sell_form');
    }
    if ($('form#edit_pos_sell_form').length > 0 || $('form#add_pos_sell_form').length > 0) {
        initialize_printer();
    }

    $('select#select_location_id').change(function() {
        reset_pos_form();

        var default_price_group = $(this).find(':selected').data('default_price_group')
        if (default_price_group) {
            if($("#price_group option[value='" + default_price_group + "']").length > 0) {
                $("#price_group").val(default_price_group);
                $("#price_group").change();
            }
        }

        //Set default invoice scheme for location
        if ($('#invoice_scheme_id').length) {
            if($('input[name="is_direct_sale"]').length > 0){
                //default scheme for sale screen
                var invoice_scheme_id = $(this).find(':selected').data('default_sale_invoice_scheme_id');
            } else {
                var invoice_scheme_id =  $(this).find(':selected').data('default_invoice_scheme_id');
            }
            
            $("#invoice_scheme_id").val(invoice_scheme_id).change();
        }

        //Set default invoice layout for location
        if ($('#invoice_layout_id').length) {
            let invoice_layout_id = $(this).find(':selected').data('default_invoice_layout_id');
            $("#invoice_layout_id").val(invoice_layout_id).change();
        }
        
        //Set default price group
        if ($('#default_price_group').length) {
            var dpg = default_price_group ?
            default_price_group : 0;
            $('#default_price_group').val(dpg);
        }

        set_payment_type_dropdown();

        if ($('#types_of_service_id').length && $('#types_of_service_id').val()) {
            $('#types_of_service_id').change();
        }
    });

    //get customer
    $('select#customer_id').select2({
        ajax: {
            url: '/contacts/customers',
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
        templateResult: function (data) { 
            var template = '';
            if (data.supplier_business_name) {
                template += data.supplier_business_name + "<br>";
            }
            template += data.text + "<br>" + LANG.mobile + ": " + data.mobile;

            if (typeof(data.total_rp) != "undefined") {
                var rp = data.total_rp ? data.total_rp : 0;
                template += "<br><i class='fa fa-gift text-success'></i> " + rp;
            }

            return  template;
        },
        minimumInputLength: 1,
        language: {
            inputTooShort: function (args) {
                return LANG.please_enter + args.minimum + LANG.or_more_characters;
            },
            noResults: function() {
                var name = $('#customer_id')
                    .data('select2')
                    .dropdown.$search.val();
                return (
                    '<button type="button" data-name="' +
                    name +
                    '" class="btn btn-link add_new_customer"><i class="fa fa-plus-circle fa-lg" aria-hidden="true"></i>&nbsp; ' +
                    __translate('add_name_as_new_customer', { name: name }) +
                    '</button>'
                );
            },
        },
        escapeMarkup: function(markup) {
            return markup;
        },
    });
    $('#customer_id').on('select2:select', function(e) {
        var data = e.params.data;
        if (data.pay_term_number) {
            $('input#pay_term_number').val(data.pay_term_number);
        } else {
            $('input#pay_term_number').val('');
        }

        if (data.pay_term_type) {
            $('#add_sell_form select[name="pay_term_type"]').val(data.pay_term_type);
            $('#edit_sell_form select[name="pay_term_type"]').val(data.pay_term_type);
        } else {
            $('#add_sell_form select[name="pay_term_type"]').val('');
            $('#edit_sell_form select[name="pay_term_type"]').val('');
        }
        
        update_shipping_address(data);
        $('#advance_balance_text').text(__currency_trans_from_en(data.balance), true);
        $('#advance_balance').val(data.balance);

        if (data.price_calculation_type == 'selling_price_group') {
            $('#price_group').val(data.selling_price_group_id);
            $('#price_group').change();
        }
        //  else {
        //     $('#price_group').val(0);
        //     $('#price_group').change();
        // }
        if ($('.contact_due_text').length) {
            get_contact_due(data.id);
            // store on customer change
            saveFormDataToLocalStorage();
        }
    });

    set_default_customer();

    if ($('#search_product').length) {
        //PosInnova: client-side cache + in-flight abort for instant POS search.
        //Keyed by (term + location + price_group + search_fields). LRU-ish via cap.
        window.__posSearchCache = window.__posSearchCache || { map: {}, order: [], cap: 80 };
        window.__posSearchInFlight = null;

        function __posCacheGet(key) {
            return window.__posSearchCache.map[key];
        }
        function __posCacheSet(key, value) {
            var c = window.__posSearchCache;
            if (!c.map.hasOwnProperty(key)) {
                c.order.push(key);
                if (c.order.length > c.cap) {
                    var evict = c.order.shift();
                    delete c.map[evict];
                }
            }
            c.map[key] = value;
        }
        //Filter a previously fetched parent result locally so narrowing
        //("co" -> "cof") updates the dropdown with zero network wait.
        function __posFilterLocally(parentResults, term) {
            var t = (term || '').toLowerCase();
            if (!t) { return parentResults; }
            return parentResults.filter(function(p) {
                var hay = ((p.name || '') + ' ' + (p.sub_sku || '') + ' ' + (p.variation || '')).toLowerCase();
                return hay.indexOf(t) !== -1;
            });
        }

        //PosInnova: PREFETCH on page load — warm the cache with the top results
        //so the very first keystroke filters locally instantly. Skips the empty-state
        //network wait users were noticing on the first character typed.
        function __posPrefetch() {
            try {
                var sf = [];
                $('.search_fields:checked').each(function(i){ sf[i] = $(this).val(); });
                var pg = '';
                if ($('#price_group').length > 0) { pg = $('#price_group').val(); }
                if ($('#default_price_group').length > 0 && pg === '') { pg = $('#default_price_group').val(); }
                var key = JSON.stringify({
                    t: '',
                    l: $('input#location_id').val(),
                    p: pg,
                    s: sf,
                    m: $('#multi_location_sale_enabled').length ? 1 : 0
                });
                if (__posCacheGet(key)) { return; } //already warm
                $.getJSON('/products/list', {
                    price_group: pg,
                    location_id: $('input#location_id').val(),
                    term: '',
                    not_for_selling: 0,
                    search_fields: sf,
                    auto_add_single: false, //IMPORTANT: don't auto-add on prefetch
                    product_row: $('input#product_row_count').val(),
                    multi_location_search: $('#multi_location_sale_enabled').length ? 1 : 0
                }, function(data) {
                    var list = data && (data.products || data);
                    if (Array.isArray(list)) {
                        __posCacheSet(key, list);
                    }
                });
            } catch (e) { /* prefetch is best-effort */ }
        }
        //Fire prefetch slightly after page render so it doesn't compete
        //with critical assets / DataTables init.
        setTimeout(__posPrefetch, 250);

        //Add Product
        $('#search_product')
            .autocomplete({
                delay: 0,
                source: function(request, response) {
                    //PosInnova: every input that shapes the request lives in
                    //pos_search_request_params(), shared with the Enter/scan path.
                    var __p = pos_search_request_params(request.term);
                    var price_group = __p.price_group;
                    var search_fields = __p.search_fields;

                    //PosInnova: build a cache key from the inputs that affect results.
                    //Skip the cache when auto_add_single might fire (single result triggers
                    //a side-effect: adds the row), so we don't accidentally re-add.
                    var __cacheKey = JSON.stringify({
                        t: (request.term || '').toLowerCase(),
                        l: $('input#location_id').val(),
                        p: price_group,
                        s: search_fields,
                        m: $('#multi_location_sale_enabled').length ? 1 : 0
                    });

                    //1) Instant return from cache if we have it.
                    var __cached = __posCacheGet(__cacheKey);
                    if (__cached) {
                        response(__cached);
                        return;
                    }

                    //2) Instant local pre-filter: if a shorter prefix is cached
                    //   (or the empty-term prefetch is warm), show its filtered subset NOW
                    //   while the real request flies.
                    var __term = (request.term || '').toLowerCase();
                    var __preFiltered = false;
                    for (var i = __term.length - 1; i >= 0; i--) {
                        var prefixKey = JSON.stringify({
                            t: __term.slice(0, i),
                            l: $('input#location_id').val(),
                            p: price_group,
                            s: search_fields,
                            m: $('#multi_location_sale_enabled').length ? 1 : 0
                        });
                        var parent = __posCacheGet(prefixKey);
                        if (parent && Array.isArray(parent)) {
                            response(__posFilterLocally(parent, request.term));
                            __preFiltered = true;
                            break;
                        }
                    }

                    //3) PosInnova: the NETWORK request waits until the keys stop.
                    //   The cache and the local pre-filter above stay instant; only the
                    //   round-trip is debounced. Before this, every character fired its
                    //   own request and aborted the previous one - but abort() only stops
                    //   the browser waiting, Apache still runs each one in full. A scanned
                    //   6-character code cost 6 server requests, and the one that mattered
                    //   queued behind 5 discarded ones (measured: 0.9s alone, 2.0-2.3s in
                    //   a scan burst). A USB scanner types a whole code inside this window,
                    //   so a scan is now ONE request; a person typing pays 150ms after
                    //   their last key, on top of the instant local results.
                    clearTimeout(window.__posSearchTimer);
                    if (window.__posSearchPendingResponse) {
                        //release jQuery UI's bookkeeping for the call this one supersedes
                        //(its index is stale, so the empty list is never displayed)
                        window.__posSearchPendingResponse([]);
                    }
                    window.__posSearchPendingResponse = __preFiltered ? null : response;

                    window.__posSearchTimer = setTimeout(function() {
                        window.__posSearchPendingResponse = null;

                        //Abort a previous TYPING request - its results are stale. Requests
                        //committed with Enter are never stored here, so typing can't kill them.
                        if (window.__posSearchInFlight && window.__posSearchInFlight.readyState !== 4) {
                            try { window.__posSearchInFlight.abort(); } catch (e) {}
                        }

                        var __sentTerm = request.term;
                        window.__posSearchInFlight = $.getJSON(
                            '/products/list',
                            //read at SEND time: the row counter may have moved during the wait
                            pos_search_request_params(__sentTerm),
                            function(data) {
                                // Check if auto-add is enabled (single product found)
                                if (data.auto_add && data.row_data) {
                                    // Automatically add the product row
                                    if (data.row_data.success) {
                                        //PosInnova: clear the box only if it still holds what was
                                        //searched - otherwise it is the next code being typed.
                                        if ($('#search_product').val() === __sentTerm) {
                                            $('#search_product').val('');
                                        }
                                        pos_add_product_row_from_data(data.row_data);
                                    } else {
                                        toastr.error(data.row_data.msg);
                                    }
                                    // Return a special marker to indicate auto-add was handled
                                    response([{auto_added: true}]);
                                } else {
                                    // Normal autocomplete response — cache plain product lists.
                                    var list = data.products || data;
                                    if (Array.isArray(list)) {
                                        __posCacheSet(__cacheKey, list);
                                    }
                                    response(list);
                                }
                            }
                        ).fail(function(xhr, status) {
                            //superseded by a newer search: release jQuery UI's bookkeeping
                            if (status === 'abort') { response([]); }
                        });
                    }, 150);
                },
                minLength: 1,
                response: function(event, ui) {
                    // Skip if auto-add already handled the product
                    if (ui.content.length == 1 && ui.content[0].auto_added) {
                        return;
                    }

                    // PosInnova: when `Disable Auto-Add Product on Search` is on, the cashier
                    // must explicitly click a suggestion for typed searches. Barcode scans are
                    // handled server-side (auto_add only on exact SKU match) so we just bail.
                    if ($('#pos_disable_auto_add').val() == 1) {
                        return;
                    }

                    if (ui.content.length == 1) {
                        ui.item = ui.content[0];

                        var is_overselling_allowed = false;
                        if($('input#is_overselling_allowed').length) {
                            is_overselling_allowed = true;
                        }
                        var for_so = false;
                        if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                            for_so = true;
                        }

                        if ((ui.item.enable_stock == 1 && ui.item.qty_available > 0) || 
                                (ui.item.enable_stock == 0) || is_overselling_allowed || for_so) {
                            $(this)
                                .data('ui-autocomplete')
                                ._trigger('select', 'autocompleteselect', ui);
                            $(this).autocomplete('close');
                        }
                    } else if (ui.content.length == 0) {
                        toastr.error(LANG.no_products_found);
                        if (!$('#__is_mobile').length) {
                            $('input#search_product').select();
                        }
                    }
                },
                focus: function(event, ui) {
                    if (ui.item.qty_available <= 0) {
                        return false;
                    }
                },
                select: function(event, ui) {
                    var searched_term = $(this).val();
                    var is_overselling_allowed = false;
                    if($('input#is_overselling_allowed').length) {
                        is_overselling_allowed = true;
                    }
                    var for_so = false;
                    if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                        for_so = true;
                    }

                    var is_draft=false;
                    if($('#status') && ($('#status').val()=='quotation' || 
                    $('#status').val()=='draft')) {
                        var is_draft=true;
                    }

                    if (ui.item.enable_stock != 1 || ui.item.qty_available > 0 || is_overselling_allowed || for_so || is_draft) {
                        $(this).val(null);

                        //Pre select lot number only if the searched term is same as the lot number
                        var purchase_line_id = ui.item.purchase_line_id && searched_term == ui.item.lot_number ? ui.item.purchase_line_id : null;
                        pos_product_row(ui.item.variation_id, purchase_line_id);
                    } else {
                        alert(LANG.out_of_stock);
                    }
                },
            })
            .autocomplete('instance')._renderItem = function(ul, item) {
                // Skip rendering if this is the auto_added marker
                if (item.auto_added) {
                    return $('<li style="display:none;">').appendTo(ul);
                }
                
                var is_overselling_allowed = false;
                if($('input#is_overselling_allowed').length) {
                    is_overselling_allowed = true;
                }

                var for_so = false;
                if ($('#sale_type').length && $('#sale_type').val() == 'sales_order') {
                    for_so = true;
                }
                var is_draft=false;
                
                if($('#status') && ($('#status').val()=='quotation' || 
                $('#status').val()=='draft')) {
                    var is_draft=true;
                }

            if (item.enable_stock == 1 && item.qty_available <= 0 && !is_overselling_allowed && !for_so && !is_draft) {
                var string = '<li class="ui-state-disabled">' + item.name;
                if (item.type == 'variable') {
                    string += '-' + item.variation;
                }
                var selling_price = item.selling_price;
                if (item.variation_group_price) {
                    selling_price = item.variation_group_price;
                }
                string +=
                    ' (' +
                    item.sub_sku +
                    ')' +
                    '<br> Price: ' +
                    __currency_trans_from_en(selling_price, false, false, __currency_precision, true) +
                    ' (Out of stock) </li>';
                return $(string).appendTo(ul);
            } else {
                var string = '<div>' + item.name;
                if (item.type == 'variable') {
                    string += '-' + item.variation;
                }

                var selling_price = item.selling_price;
                if (item.variation_group_price) {
                    selling_price = item.variation_group_price;
                }

                string += ' (' + item.sub_sku + ')' + '<br> Price: ' + __currency_trans_from_en(selling_price, false, false, __currency_precision, true);
                if (item.enable_stock == 1) {
                    var qty_available = __currency_trans_from_en(item.qty_available, false, false, __currency_precision, true);
                    string += ' - ' + qty_available + item.unit;
                }
                //PosInnova: when multi-location search is on, show per-location stock breakdown
                if (item.location_qtys && item.location_qtys.length > 0) {
                    string += '<br><small style="color:#555;">';
                    var parts = [];
                    for (var i = 0; i < item.location_qtys.length; i++) {
                        var lq = item.location_qtys[i];
                        parts.push('<b>' + lq.location_name + ':</b> ' + __currency_trans_from_en(lq.qty_available, false, false, __currency_precision, true) + (item.unit || ''));
                    }
                    string += parts.join(' &nbsp;|&nbsp; ');
                    string += '</small>';
                }
                string += '</div>';

                return $('<li>')
                    .append(string)
                    .appendTo(ul);
            }
        };
    }

    //Update line total and check for quantity not greater than max quantity
    $('table#pos_table tbody').on('change', 'input.pos_quantity', function() {
        // comment line becouse it validate form at increment and decrement item
        // if (sell_form_validator) {
        //     sell_form.valid();
        // }
        if (pos_form_validator) {
            pos_form_validator.element($(this));
        }
        // var max_qty = parseFloat($(this).data('rule-max'));
        var entered_qty = __read_number($(this));
        

        var tr = $(this).parents('tr');

        var unit_price_inc_tax = __read_number(tr.find('input.pos_unit_price_inc_tax'));
        var line_total = entered_qty * unit_price_inc_tax;

        __write_number(tr.find('input.pos_line_total'), line_total, false);
        tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));

        //Change modifier quantity
        tr.find('.modifier_qty_text').each( function(){
            $(this).text(__currency_trans_from_en(entered_qty, false));
        });
        tr.find('.modifiers_quantity').each( function(){
            $(this).val(entered_qty);
        });

        pos_total_row();

        adjustComboQty(tr);
    });

    //If change in unit price update price including tax and line total
    $('table#pos_table tbody').on('change', 'input.pos_unit_price', function() {
        var unit_price = __read_number($(this));
        var tr = $(this).parents('tr');

        //calculate discounted unit price
        var discounted_unit_price = calculate_discounted_unit_price(tr);

        var tax_rate = tr
            .find('select.tax_id')
            .find(':selected')
            .data('rate');
        var quantity = __read_number(tr.find('input.pos_quantity'));

        var unit_price_inc_tax = __add_percent(discounted_unit_price, tax_rate);
        var line_total = quantity * unit_price_inc_tax;

        __write_number(tr.find('input.pos_unit_price_inc_tax'), unit_price_inc_tax);
        __write_number(tr.find('input.pos_line_total'), line_total);
        tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));
        pos_each_row(tr);
        pos_total_row();
        round_row_to_iraqi_dinnar(tr);
    });

    //If change in tax rate then update unit price according to it.
    $('table#pos_table tbody').on('change', 'select.tax_id', function() {
        var tr = $(this).parents('tr');

        var tax_rate = tr
            .find('select.tax_id')
            .find(':selected')
            .data('rate');
        var unit_price_inc_tax = __read_number(tr.find('input.pos_unit_price_inc_tax'));

        var discounted_unit_price = __get_principle(unit_price_inc_tax, tax_rate);
        var unit_price = get_unit_price_from_discounted_unit_price(tr, discounted_unit_price);
        __write_number(tr.find('input.pos_unit_price'), unit_price);
        pos_each_row(tr);
    });

    //If change in unit price including tax, update unit price
    $('table#pos_table tbody').on('change', 'input.pos_unit_price_inc_tax', function() {
        var unit_price_inc_tax = __read_number($(this));

        if (iraqi_selling_price_adjustment) {
            unit_price_inc_tax = round_to_iraqi_dinnar(unit_price_inc_tax);
            __write_number($(this), unit_price_inc_tax);
        }

        var tr = $(this).parents('tr');

        var tax_rate = tr
            .find('select.tax_id')
            .find(':selected')
            .data('rate');
        var quantity = __read_number(tr.find('input.pos_quantity'));

        var line_total = quantity * unit_price_inc_tax;
        var discounted_unit_price = __get_principle(unit_price_inc_tax, tax_rate);
        var unit_price = get_unit_price_from_discounted_unit_price(tr, discounted_unit_price);

        __write_number(tr.find('input.pos_unit_price'), unit_price);
        __write_number(tr.find('input.pos_line_total'), line_total, false);
        tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));

        pos_each_row(tr);
        pos_total_row();
    });

    //Change max quantity rule if lot number changes
    $('table#pos_table tbody').on('change', 'select.lot_number', function() {
        var qty_element = $(this)
            .closest('tr')
            .find('input.pos_quantity');

        var tr = $(this).closest('tr');
        var multiplier = 1;
        var unit_name = '';
        var sub_unit_length = tr.find('select.sub_unit').length;
        if (sub_unit_length > 0) {
            var select = tr.find('select.sub_unit');
            multiplier = parseFloat(select.find(':selected').data('multiplier'));
            unit_name = select.find(':selected').data('unit_name');
        }
        var allow_overselling = qty_element.data('allow-overselling');
        if ($(this).val() && !allow_overselling) {
            var lot_qty = $('option:selected', $(this)).data('qty_available');
            var max_err_msg = $('option:selected', $(this)).data('msg-max');

            if (sub_unit_length > 0) {
                lot_qty = lot_qty / multiplier;
                var lot_qty_formated = __number_f(lot_qty, false);
                max_err_msg = __translate('lot_max_qty_error', {
                    max_val: lot_qty_formated,
                    unit_name: unit_name,
                });
            }

            qty_element.attr('data-rule-max-value', lot_qty);
            qty_element.attr('data-msg-max-value', max_err_msg);

            qty_element.rules('add', {
                'max-value': lot_qty,
                messages: {
                    'max-value': max_err_msg,
                },
            });
        } else {
            var default_qty = qty_element.data('qty_available');
            var default_err_msg = qty_element.data('msg_max_default');
            if (sub_unit_length > 0) {
                default_qty = default_qty / multiplier;
                var lot_qty_formated = __number_f(default_qty, false);
                default_err_msg = __translate('pos_max_qty_error', {
                    max_val: lot_qty_formated,
                    unit_name: unit_name,
                });
            }

            qty_element.attr('data-rule-max-value', default_qty);
            qty_element.attr('data-msg-max-value', default_err_msg);

            qty_element.rules('add', {
                'max-value': default_qty,
                messages: {
                    'max-value': default_err_msg,
                },
            });
        }
        qty_element.trigger('change');
    });

    //Change in row discount type or discount amount
    $('table#pos_table tbody').on(
        'change',
        'select.row_discount_type, input.row_discount_amount',
        function() {
            var tr = $(this).parents('tr');

            //calculate discounted unit price
            var discounted_unit_price = calculate_discounted_unit_price(tr);

            var tax_rate = tr
                .find('select.tax_id')
                .find(':selected')
                .data('rate');
            var quantity = __read_number(tr.find('input.pos_quantity'));

            var unit_price_inc_tax = __add_percent(discounted_unit_price, tax_rate);
            var line_total = quantity * unit_price_inc_tax;

            __write_number(tr.find('input.pos_unit_price_inc_tax'), unit_price_inc_tax);
            __write_number(tr.find('input.pos_line_total'), line_total, false);
            tr.find('span.pos_line_total_text').text(__currency_trans_from_en(line_total, true));
            pos_each_row(tr);
            pos_total_row();
            round_row_to_iraqi_dinnar(tr);
        }
    );

    //Remove row on click on remove row
    $('table#pos_table tbody').on('click', 'i.pos_remove_row', function() {
        $(this)
            .parents('tr')
            .remove();
        pos_total_row();
    });

    //Cancel the invoice
    $('button#pos-cancel').click(function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(confirm => {
            if (confirm) {
                reset_pos_form();
            }
        });
    });

    //Preview invoice (no save)
    $(document).on('click', '#pos-preview-invoice', function() {
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning('Please add at least one product to preview.');
            return;
        }
        var customer_name = $('select#customer_id option:selected').text() || '';
        var location_name = $('#location_id').data('location_name') || $('#select_location_id option:selected').text() || '';
        var sale_date = $('input[name="transaction_date"]').val() || '';
        var business_name = $('meta[name="business_name"]').attr('content') || '';

        // Show per-line "Sold From" column only when multi-location sale is enabled
        // (i.e. at least one row has the per-line location selector rendered).
        var show_line_location = $('table#pos_table tbody tr.product_row .row_line_location').length > 0;

        var rows_html = '';
        var sn = 1;
        $('table#pos_table tbody tr.product_row').each(function() {
            var $tr = $(this);
            var name = '';
            // Try to extract product name from first td: clone, strip child elements,
            // then split on <br> so we keep ONLY the product name (not the SKU/brand line).
            var $clone = $tr.find('td').first().clone();
            $clone.find('img, input, select, button, textarea, table, .modal, .row, small, i').remove();
            // Replace <br> with a unique splitter so we can keep just the first segment
            $clone.find('br').replaceWith('|||BR|||');
            var raw = ($clone.text() || '').replace(/\s+/g, ' ').trim();
            if (raw) {
                name = raw.split('|||BR|||')[0].trim();
                if (name.length > 120) name = name.substr(0, 120) + '…';
            }
            var qty = ($tr.find('input.pos_quantity').val() || '1');
            var unit = ($tr.find('select.sub_unit option:selected').text() || $tr.find('.row_unit_text').first().text() || '').trim();
            var price = ($tr.find('input.pos_unit_price').val() || '0');
            var line_total = ($tr.find('span.pos_line_total_text').text() || $tr.find('input.pos_line_total').val() || '0');
            var line_loc = '';
            if (show_line_location) {
                var $loc_sel = $tr.find('.row_line_location');
                line_loc = ($loc_sel.find('option:selected').text() || '').trim();
            }
            rows_html += '<tr>'
                + '<td class="text-center">' + sn + '</td>'
                + '<td>' + $('<div>').text(name).html() + '</td>'
                + (show_line_location ? ('<td>' + $('<div>').text(line_loc).html() + '</td>') : '')
                + '<td class="text-right">' + qty + ' ' + $('<div>').text(unit).html() + '</td>'
                + '<td class="text-right">' + price + '</td>'
                + '<td class="text-right">' + line_total + '</td>'
                + '</tr>';
            sn++;
        });

        var subtotal = $('span.price_total').first().text() || '0';
        var discount = $('span#total_discount').first().text() || '0';
        var tax = $('span#order_tax').first().text() || '0';
        var shipping = $('span#shipping_charges_amount').first().text() || '';
        var total = $('span#total_payable').first().text() || '0';

        // Numeric versions to decide whether to render tax/shipping rows.
        // Some locales use "." as thousands separator, so strip everything except
        // digits and the last separator before parsing.
        var to_num = function(s) {
            if (!s) return 0;
            var n = parseFloat(String(s).replace(/[^0-9\-.,]/g, '').replace(/,/g, ''));
            return isNaN(n) ? 0 : n;
        };
        var tax_num = to_num(tax);
        var shipping_num = to_num(shipping);

        var html = '<div class="invoice-preview" style="font-family: Arial, sans-serif;">'
            + (business_name ? ('<h3 class="text-center" style="margin:0 0 4px;">' + $('<div>').text(business_name).html() + '</h3>') : '')
            + '<div class="text-center" style="margin-bottom:10px;"><strong>' + (location_name ? $('<div>').text(location_name).html() : '') + '</strong></div>'
            + '<div class="row" style="margin-bottom:10px;">'
            +    '<div class="col-xs-6"><strong>Customer:</strong> ' + $('<div>').text(customer_name).html() + '</div>'
            +    '<div class="col-xs-6 text-right"><strong>Date:</strong> ' + $('<div>').text(sale_date).html() + '</div>'
            + '</div>'
            + '<table class="table table-bordered table-condensed" style="margin-bottom:10px;">'
            +    '<thead><tr>'
            +       '<th class="text-center" style="width:5%;">#</th>'
            +       '<th>Product</th>'
            +       (show_line_location ? '<th style="width:18%;">Sold From</th>' : '')
            +       '<th class="text-right" style="width:15%;">Qty</th>'
            +       '<th class="text-right" style="width:15%;">Unit Price</th>'
            +       '<th class="text-right" style="width:15%;">Subtotal</th>'
            +    '</tr></thead>'
            +    '<tbody>' + rows_html + '</tbody>'
            + '</table>'
            + '<div class="row"><div class="col-xs-6 col-xs-offset-6">'
            +    '<table class="table table-condensed">'
            +       '<tr><td><strong>Subtotal:</strong></td><td class="text-right">' + subtotal + '</td></tr>'
            +       '<tr><td><strong>Discount:</strong></td><td class="text-right">' + discount + '</td></tr>'
            +       (tax_num > 0 ? ('<tr><td><strong>Tax:</strong></td><td class="text-right">' + tax + '</td></tr>') : '')
            +       (shipping_num > 0 ? ('<tr><td><strong>Shipping:</strong></td><td class="text-right">' + shipping + '</td></tr>') : '')
            +       '<tr style="border-top:2px solid #333;"><td><strong>Total:</strong></td><td class="text-right"><strong>' + total + '</strong></td></tr>'
            +    '</table>'
            + '</div></div>'
            + '<div class="alert alert-info" style="margin-top:10px;font-size:12px;"><i class="fa fa-info-circle"></i> Preview only — nothing has been saved yet. Click "Finalize Payment" or any checkout button to complete the sale.</div>'
            + '</div>';

        $('#invoice_preview_body').html(html);
        $('#invoice_preview_modal').modal('show');
    });

    //Save invoice as draft
    $('button#pos-draft').click(function() {
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        var is_valid = isValidPosForm();
        if (is_valid != true) {
            return;
        }

        //PosInnova: renumber rows so no cart line can be lost to a duplicate index.
        pos_reindex_product_rows();

        var data = pos_form_obj.serialize();
        data = data + '&status=draft';
        var url = pos_form_obj.attr('action');

        disable_pos_form_actions();
        $.ajax({
            method: 'POST',
            url: url,
            data: data,
            dataType: 'json',
            success: function(result) {
                enable_pos_form_actions();
                if (result.success == 1) {
                    reset_pos_form();
                    toastr.success(result.msg);
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    //Save invoice as Quotation
    $('button#pos-quotation').click(function() {
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        var is_valid = isValidPosForm();
        if (is_valid != true) {
            return;
        }

        //PosInnova: renumber rows so no cart line can be lost to a duplicate index.
        pos_reindex_product_rows();

        var data = pos_form_obj.serialize();
        data = data + '&status=quotation';
        var url = pos_form_obj.attr('action');

        disable_pos_form_actions();
        $.ajax({
            method: 'POST',
            url: url,
            data: data,
            dataType: 'json',
            success: function(result) {
                enable_pos_form_actions();
                if (result.success == 1) {
                    reset_pos_form();
                    toastr.success(result.msg);

                    //Check if enabled or not
                    if (result.receipt.is_enabled) {
                        pos_print(result.receipt);
                    }
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    //Finalize invoice, open payment modal
    $('button#pos-finalize').click(function() {
        if (window.is_pos_submitting === true) { return false; }
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        if ($('#reward_point_enabled').length) {
            var validate_rp = isValidatRewardPoint();
            if (!validate_rp['is_valid']) {
                toastr.error(validate_rp['msg']);
                return false;
            }
        }

        if (!isValidPosForm()) {
            return false;
        }

        $('#modal_payment').modal('show');
    });

    $('#modal_payment').one('shown.bs.modal', function() {
        $('#modal_payment')
            .find('input')
            .filter(':visible:first')
            .focus()
            .select();
        if ($('form#edit_pos_sell_form').length == 0) {
            $(this).find('#method_0').change();
        }
    });

    //Finalize without showing payment options
    $('button.pos-express-finalize').click(function() {
        if (window.is_pos_submitting === true) { return false; }

        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        if ($('#reward_point_enabled').length) {
            var validate_rp = isValidatRewardPoint();
            if (!validate_rp['is_valid']) {
                toastr.error(validate_rp['msg']);
                return false;
            }
        }

        if (!isValidPosForm()) {
            return false;
        }

        var pay_method = $(this).data('pay_method');

        //If pay method is credit sale submit form
        if (pay_method == 'credit_sale') {
            $('#is_credit_sale').val(1);
            pos_form_obj.submit();
            return true;
        } else {
            if ($('#is_credit_sale').length) {
                $('#is_credit_sale').val(0);
            }
        }

        //Check for remaining balance & add it in 1st payment row
        var total_payable = __read_number($('input#final_total_input'));
        var total_paying = __read_number($('input#total_paying_input'));
        if (total_payable > total_paying) {
            var bal_due = total_payable - total_paying;

            var first_row = $('#payment_rows_div')
                .find('.payment-amount')
                .first();
            var first_row_val = __read_number(first_row);
            first_row_val = first_row_val + bal_due;
            __write_number(first_row, first_row_val);
            first_row.trigger('change');
        }

        //Change payment method.
        var payment_method_dropdown = $('#payment_rows_div')
            .find('.payment_types_dropdown')
            .first();
        
            payment_method_dropdown.val(pay_method);
            payment_method_dropdown.change();
        if (pay_method == 'card') {
            $('div#card_details_modal').modal('show');
        } else if (pay_method == 'suspend') {
            $('div#confirmSuspendModal').modal('show');
        } else {
            pos_form_obj.submit();
        }
    });

    $('div#card_details_modal').on('shown.bs.modal', function(e) {
        $('input#card_number').focus();
    });

    $('div#confirmSuspendModal').on('shown.bs.modal', function(e) {
        $(this)
            .find('textarea')
            .focus();
    });

    //on save card details
    $('button#pos-save-card').click(function() {
        $('input#card_number_0').val($('#card_number').val());
        $('input#card_holder_name_0').val($('#card_holder_name').val());
        $('input#card_transaction_number_0').val($('#card_transaction_number').val());
        $('select#card_type_0').val($('#card_type').val());
        $('input#card_month_0').val($('#card_month').val());
        $('input#card_year_0').val($('#card_year').val());
        $('input#card_security_0').val($('#card_security').val());

        $('div#card_details_modal').modal('hide');
        pos_form_obj.submit();
    });

    $('button#pos-suspend').click(function() {
        $('input#is_suspend').val(1);
        $('div#confirmSuspendModal').modal('hide');
        pos_form_obj.submit();
        $('input#is_suspend').val(0);
    });

    //fix select2 input issue on modal
    $('#modal_payment')
        .find('.select2')
        .each(function() {
            $(this).select2({
                dropdownParent: $('#modal_payment'),
            });
        });

    $('button#add-payment-row').click(function() {
        var row_index = $('#payment_row_index').val();
        var location_id = $('input#location_id').val();
        $.ajax({
            method: 'POST',
            url: '/sells/pos/get_payment_row',
            data: { row_index: row_index, location_id: location_id },
            dataType: 'html',
            success: function(result) {
                if (result) {
                    var appended = $('#payment_rows_div').append(result);

                    var total_payable = __read_number($('input#final_total_input'));
                    var total_paying = __read_number($('input#total_paying_input'));
                    var b_due = total_payable - total_paying;
                    $(appended)
                        .find('input.payment-amount')
                        .focus();
                    $(appended)
                        .find('input.payment-amount')
                        .last()
                        .val(__currency_trans_from_en(b_due, false))
                        .change()
                        .select();
                    __select2($(appended).find('.select2'));
                    $(appended).find('#method_' + row_index).change();
                    $('#payment_row_index').val(parseInt(row_index) + 1);
                }
            },
        });
    });

    $(document).on('click', '.remove_payment_row', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                $(this)
                    .closest('.payment_row')
                    .remove();
                calculate_balance_due();
            }
        });
    });

    pos_form_validator = pos_form_obj.validate({
        submitHandler: function(form) {
            // Guard: block re-entry from rapid clicks / shortcut spamming / queued submits
            if (window.is_pos_submitting === true) {
                return false;
            }

            if (!isValidPosForm()) {
                return false;
            }

            // Confirmation step: show invoice preview before finalising the sale.
            // Skipped for suspended sales (just parking the order) and when the
            // user has already clicked "Confirm & Finalize" in the preview modal.
            var is_suspend_val = $('input#is_suspend').val();
            if (window.pos_preview_confirmed !== true && is_suspend_val != 1) {
                show_pos_preview_for_confirmation();
                return false;
            }
            // consume the one-shot confirmation flag so the next sale must re-confirm
            window.pos_preview_confirmed = false;

            // var total_payble = __read_number($('input#final_total_input'));
            // var total_paying = __read_number($('input#total_paying_input'));
            var cnf = true;

            //Ignore if the difference is less than 0.5
            if ($('input#in_balance_due').val() >= 0.5) {
                cnf = confirm(LANG.paid_amount_is_less_than_payable);
                // if( total_payble > total_paying ){
                // 	cnf = confirm( LANG.paid_amount_is_less_than_payable );
                // } else if(total_payble < total_paying) {
                // 	alert( LANG.paid_amount_is_more_than_payable );
                // 	cnf = false;
                // }
            }

            var total_advance_payments = 0;
            $('#payment_rows_div').find('select.payment_types_dropdown').each( function(){
                if ($(this).val() == 'advance') {
                    total_advance_payments++
                };
            });

            if (total_advance_payments > 1) {
                alert(LANG.advance_payment_cannot_be_more_than_once);
                return false;
            }

            var is_msp_valid = true;
            //Validate minimum selling price if hidden
            $('.pos_unit_price_inc_tax').each( function(){
                if (!$(this).is(":visible") && $(this).data('rule-min-value')) {
                    var val = __read_number($(this));
                    var error_msg_td = $(this).closest('tr').find('.pos_line_total_text').closest('td');
                    if (val > $(this).data('rule-min-value')) {
                        is_msp_valid = false;
                        error_msg_td.append( '<label class="error">' + $(this).data('msg-min-value') + '</label>');
                    } else {
                        error_msg_td.find('label.error').remove();
                    }
                }
            });

            if (!is_msp_valid) {
                return false;
            }

            if (cnf) {
                disable_pos_form_actions();
                window.is_pos_submitting = true;

                //PosInnova: renumber rows so no cart line can be lost to a duplicate index.
                pos_reindex_product_rows();

                var data = $(form).serialize();
                data = data + '&status=final';
                var url = $(form).attr('action');
                $.ajax({
                    method: 'POST',
                    url: url,
                    data: data,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success == 1) {
                            if (result.whatsapp_link) {
                                window.open(result.whatsapp_link);
                            }
                            //PosInnova: read the totals before the form is cleared
                            pos_display_announce_sale(result);
                            $('#modal_payment').modal('hide');
                            toastr.success(result.msg);

                            reset_pos_form();

                            //Check if enabled or not
                            if (result.receipt.is_enabled) {
                                pos_print(result.receipt);
                            }
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                    error: function(xhr) {
                        // Network failure / 4xx / 5xx — let the user retry instead of locking the form
                        var msg = (xhr && xhr.responseJSON && xhr.responseJSON.msg)
                            ? xhr.responseJSON.msg
                            : (LANG.something_went_wrong || 'Network error. Please check your connection and try again.');
                        toastr.error(msg);
                    },
                    complete: function() {
                        window.is_pos_submitting = false;
                        enable_pos_form_actions();
                    },
                });
            }
            return false;
        },
    });

    $(document).on('input', '.payment-amount', function(e) {
        if (e.originalEvent) {
            $(this).data('pi-user-typed', true);
        }
    });
    $(document).on('change', '.payment-amount', function() {
        calculate_balance_due();
    });

    //Update discount
    $('button#posEditDiscountModalUpdate').click(function() {

        //if discount amount is not valid return false
        if (!$("#discount_amount_modal").valid()) {
            return false;
        }
        //Close modal
        $('div#posEditDiscountModal').modal('hide');

        //Update values
        $('input#discount_type').val($('select#discount_type_modal').val());
        __write_number($('input#discount_amount'), __read_number($('input#discount_amount_modal')));

        if ($('#reward_point_enabled').length) {
            var reward_validation = isValidatRewardPoint();
            if (!reward_validation['is_valid']) {
                toastr.error(reward_validation['msg']);
                $('#rp_redeemed_modal').val(0);
                $('#rp_redeemed_modal').change();
            }
            updateRedeemedAmount();
        }

        pos_total_row();
    });

    //Shipping
    $('button#posShippingModalUpdate').click(function() {
        //Close modal
        $('div#posShippingModal').modal('hide');

        //update shipping details
        $('input#shipping_details').val($('#shipping_details_modal').val());

        $('input#shipping_address').val($('#shipping_address_modal').val());
        $('input#shipping_status').val($('#shipping_status_modal').val());
        $('input#delivered_to').val($('#delivered_to_modal').val());
        $('input#delivery_person').val($('#delivery_person_modal').val());

        //Update shipping charges
        __write_number(
            $('input#shipping_charges'),
            __read_number($('input#shipping_charges_modal'))
        );

        //$('input#shipping_charges').val(__read_number($('input#shipping_charges_modal')));

        pos_total_row();
    });

    $('#posShippingModal').on('shown.bs.modal', function() {
        $('#posShippingModal')
            .find('#shipping_details_modal')
            .filter(':visible:first')
            .focus()
            .select();
            // $('.select2-selection__rendered').css('padding-right', '150px');
    });

    $(document).on('shown.bs.modal', '.row_edit_product_price_model', function() {
        $('.row_edit_product_price_model')
            .find('input')
            .filter(':visible:first')
            .focus()
            .select();
    });

    //Update Order tax
    $('button#posEditOrderTaxModalUpdate').click(function() {
        //Close modal
        $('div#posEditOrderTaxModal').modal('hide');

        var tax_obj = $('select#order_tax_modal');
        var tax_id = tax_obj.val();
        var tax_rate = tax_obj.find(':selected').data('rate');

        $('input#tax_rate_id').val(tax_id);

        __write_number($('input#tax_calculation_amount'), tax_rate);
        pos_total_row();
    });

    $(document).on('click', '.add_new_customer', function() {
        $('#customer_id').select2('close');
        var name = $(this).data('name');
        $('.contact_modal')
            .find('input#name')
            .val(name);
        $('.contact_modal')
            .find('select#contact_type')
            .val('customer')
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
                                return $('#hidden_id').val() || '';
                            },
                        },
                    },
                },
                // tax_number remote validation removed - now handled with sweet alert
            },
            messages: {
                contact_id: {
                    required: LANG.contact_id_required,
                    remote: LANG.contact_id_already_exists,
                },
            },
            submitHandler: function(form) {
                checkTaxNumberAndSubmitQuick(form);
            },
        });

    function checkTaxNumberAndSubmitQuick(form) {
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
                                checkMobileAndSubmitQuick(form);
                            } else {
                                $('#tax_number').select();
                            }
                        });
                    } else {
                        checkMobileAndSubmitQuick(form);
                    }
                },
            });
        } else {
            // If no tax number, proceed to mobile check
            checkMobileAndSubmitQuick(form);
        }
    }

    function checkMobileAndSubmitQuick(form) {
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
                            submitQuickContactForm(form);
                        } else {
                            $('#mobile').select();
                        }
                    });
                    
                } else {
                    submitQuickContactForm(form);
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

    //Updates for add sell
    $('select#discount_type, input#discount_amount, input#shipping_charges, \
        input#rp_redeemed_amount').change(function() {
        pos_total_row();
    });
    $('select#tax_rate_id').change(function() {
        var tax_rate = $(this)
            .find(':selected')
            .data('rate');
        __write_number($('input#tax_calculation_amount'), tax_rate);
        pos_total_row();
    });
    //Datetime picker
    $('#transaction_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });

    //Direct sell submit
    sell_form = $('form#add_sell_form');
    if ($('form#edit_sell_form').length) {
        sell_form = $('form#edit_sell_form');
        pos_total_row();
    }
    sell_form_validator = sell_form.validate({
        rules: {
            invoice_no: {
                remote: {
                    url: '/sell/check-invoice-number',
                    type: 'post',
                    data: {
                        invoice_no: function() {
                            return $('#invoice_no').val();
                        },
                        transaction_id: function() {
                            var id = '';
                            var editForm = $('form#edit_sell_form');
                            if (editForm.length) {
                                id = editForm.data('transaction-id');
                            }
                            return id || '';
                        }
                    }
                }
            },
        },
        messages: {
            invoice_no: {
                remote: LANG.invoice_number_already_exists,
            },
        },
    });

    $('button#submit-sell, button#save-and-print').click(function(e) {
        //Check if product is present or not.
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            toastr.warning(LANG.no_products_added);
            return false;
        }

        var is_msp_valid = true;
        //Validate minimum selling price if hidden
        $('.pos_unit_price_inc_tax').each( function(){
            if (!$(this).is(":visible") && $(this).data('rule-min-value')) {
                var val = __read_number($(this));
                var error_msg_td = $(this).closest('tr').find('.pos_line_total_text').closest('td');
                if (val > $(this).data('rule-min-value')) {
                    is_msp_valid = false;
                    error_msg_td.append( '<label class="error">' + $(this).data('msg-min-value') + '</label>');
                } else {
                    error_msg_td.find('label.error').remove();
                }
            }
        });

        if (!is_msp_valid) {
            return false;
        }

        if ($(this).attr('id') == 'save-and-print') {
            $('#is_save_and_print').val(1);           
        } else {
            $('#is_save_and_print').val(0);
        }

        if ($('#reward_point_enabled').length) {
            var validate_rp = isValidatRewardPoint();
            if (!validate_rp['is_valid']) {
                toastr.error(validate_rp['msg']);
                return false;
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

        //PosInnova: renumber rows so no cart line can be lost to a duplicate index.
        pos_reindex_product_rows();

        if (sell_form.valid()) {
            var $sell_btn = $(this);
            var save_sale = function () {
                window.onbeforeunload = null;
                $sell_btn.attr('disabled', true);
                sell_form.submit();
            };
            // PosInnova "check before recording": a final sale (drafts and quotations record no money)
            var sale_status = $('select[name="status"]').length ? $('select[name="status"]').val() : $('input[name="status"]').val();
            if (window.PosConfirm && PosConfirm.enabled('sell') && (!sale_status || sale_status == 'final')) {
                var rows = [];
                var customer = $.trim($('#customer_id option:selected').text());
                if (customer) { rows.push([PosConfirm.label('customer'), customer]); }
                rows.push([PosConfirm.label('items'), String($('table#pos_table tbody .product_row').length)]);
                var $pay = $('#payment_rows_div .payment_row, .payment_row').filter(':visible').first();
                if ($pay.length) {
                    var paid = __read_number($pay.find('.payment-amount')) || 0;
                    rows.push([PosConfirm.label('paid'), __currency_trans_from_en(paid, true)]);
                    var method = $.trim($pay.find('.payment_types_dropdown option:selected').text());
                    if (method) { rows.push([PosConfirm.label('method'), method]); }
                }
                PosConfirm.ask({ kind: 'sell', amount: __read_number($('#final_total_input')) || 0, rows: rows }, save_sale);
            } else {
                save_sale();
            }
        }
    });

    //REPAIR MODULE:check if repair module field is present send data to filter product
    var is_enabled_stock = null;
    if ($("#is_enabled_stock").length) {
        is_enabled_stock = $("#is_enabled_stock").val();
    }

    var device_model_id = null;
    if ($("#repair_model_id").length) {
        device_model_id = $("#repair_model_id").val();
    }

    //Show product list.
    get_product_suggestion_list(
        global_p_category_id,
        global_brand_id,
        $('input#location_id').val(),
        null,
        is_enabled_stock,
        device_model_id
    );
    
    $('select#select_location_id').on('change', function(e) {
        $('input#suggestion_page').val(1);
        var location_id = $('input#location_id').val();
        if (location_id != '' || location_id != undefined) {
            get_product_suggestion_list(
                global_p_category_id,
                global_brand_id,
                $('input#location_id').val(),
                null
            );
        }
        get_featured_products();
    });

// on click sub category in category drawer
    $('.product_category').on('click', function(e) {
        global_p_category_id = $(this).data('value');
        $('input#suggestion_page').val(1);
        var location_id = $('input#location_id').val();
        if (location_id != '' || location_id != undefined) {
            get_product_suggestion_list(
                global_p_category_id,
                global_brand_id,
                $('input#location_id').val(),
                null
            );
        }
        get_featured_products();
        $('.overlay-category').trigger('click');
    });

    //  function for show sub category 
    $('.main-category').on('click', function(){

        global_p_category_id = $(this).data('value');
        parent = $(this).data('parent');

        if (parent == 0) {
            get_product_suggestion_list(
                global_p_category_id,
                global_brand_id,
                $('input#location_id').val(),
                null
            );
            get_featured_products();
            $('.overlay-category').trigger('click');
        }
        else {
            var main_category = $(this).data('value');

            $('.main-category-div').hide();
            $('.all-sub-category').hide();
            $('.all-sub-category[data-category-id="' + main_category + '"]').fadeIn();
            $('.category_heading').text('Sub Category ' + $(this).data('name'));
            $('.category-back').fadeIn();
        }
    })

    // function for back button in category 
    $('.category-back').on('click', function(){
        $('.main-category-div').fadeIn();
        $('.main-category-all').fadeIn();
        $('.all-sub-category').hide();
        $('.category-back').hide();
        $('.category_heading').text('Category');
    });

    // on click brand in brand drawer 
    $('.product_brand').on('click', function(e) {
        global_brand_id = $(this).data('value');
        $('input#suggestion_page').val(1);
        var location_id = $('input#location_id').val();

        if (location_id != '' || location_id != undefined) {
            get_product_suggestion_list(
                global_p_category_id,
                global_brand_id,
                $('input#location_id').val(),
                null
            );
        }
        get_featured_products();
        $('.overlay-brand').trigger('click');
    });

    // close side bar 

    $('.close-side-bar-category').on('click', function() {
        $('.overlay-category').trigger('click');
    });

    $('.close-side-bar-brand').on('click', function() {
        $('.overlay-brand').trigger('click');
    });


    

    $(document).on('click', 'div.product_box', function() {
        //Check if location is not set then show error message.
        if ($('input#location_id').val() == '') {
            toastr.warning(LANG.select_location);
        } else {
            pos_product_row($(this).data('variation_id'));
        }
    });

    $(document).on('shown.bs.modal', '.row_description_modal', function() {
        $(this)
            .find('textarea')
            .first()
            .focus();
    });

    //PosInnova: Enter commits the search box - see pos_commit_search_term(). Bound
    //on document, so it runs AFTER jQuery UI's own keydown: when the suggestion list
    //has a highlighted item, jQuery UI has already selected it and prevented the
    //default, and that case is left exactly as it was.
    $(document).on('keydown', '#search_product', function(e) {
        if (e.which !== 13 || e.isDefaultPrevented()) {
            return;
        }
        var term = String($(this).val() || '').trim();
        if (!term) {
            return;
        }
        e.preventDefault();
        pos_commit_search_term(term);
    });

    //Press enter on search product to jump into last quantty and vice-versa
    $('#search_product').keydown(function(e) {
        var key = e.which;
        if (key == 9) {
            // the tab key code
            e.preventDefault();
            if ($('#pos_table tbody tr').length > 0) {
                $('#pos_table tbody tr:last')
                    .find('input.pos_quantity')
                    .focus()
                    .select();
            }
        }
    });
    $('#pos_table').on('keypress', 'input.pos_quantity', function(e) {
        var key = e.which;
        if (key == 13) {
            // the enter key code
            if (!$('#__is_mobile').length) {
                $('#search_product').focus();
            }
        }
    });

    $('#exchange_rate').change(function() {
        var curr_exchange_rate = 1;
        if ($(this).val()) {
            curr_exchange_rate = __read_number($(this));
        }
        var total_payable = __read_number($('input#final_total_input'));
        var shown_total = total_payable * curr_exchange_rate;
        $('span#total_payable').text(__currency_trans_from_en(shown_total, false));
    });

    $('select#price_group').change(function() {
        $('input#hidden_price_group').val($(this).val());
    });

    //Quick add product
    $(document).on('click', 'button.pos_add_quick_product', function() {
        var url = $(this).data('href');
        var container = $(this).data('container');
        $.ajax({
            url: url + '?product_for=pos',
            dataType: 'html',
            success: function(result) {
                $(container)
                    .html(result)
                    .modal('show');
                $('.os_exp_date').datepicker({
                    autoclose: true,
                    format: 'dd-mm-yyyy',
                    clearBtn: true,
                });
            },
        });
    });

    $(document).on('change', 'form#quick_add_product_form input#single_dpp', function() {
        var unit_price = __read_number($(this));
        $('table#quick_product_opening_stock_table tbody tr').each(function() {
            var input = $(this).find('input.unit_price');
            __write_number(input, unit_price);
            input.change();
        });
    });

    $(document).on('quickProductAdded', function(e) {
        //Check if location is not set then show error message.
        if ($('input#location_id').val() == '') {
            toastr.warning(LANG.select_location);
        } else {
            pos_product_row(e.variation.id);
        }
    });

    $('div.view_modal').on('show.bs.modal', function() {
        __currency_convert_recursively($(this));
    });

    //Multi-location sale: when per-row location changes, refresh "in stock" display & max qty rule for that row.
    $('table#pos_table').on('change', 'select.row_line_location', function() {
        var $select = $(this);
        var tr = $select.closest('tr.product_row');
        var variation_id = $select.data('variation_id');
        var location_id = $select.val();
        if (!variation_id || !location_id) {
            return;
        }
        $.getJSON('/sells/pos/get-variation-location-qty/' + variation_id + '/' + location_id, function(resp) {
            if (!resp || !resp.success) {
                return;
            }
            var $wrap = tr.find('.row_in_stock_wrap');
            if ($wrap.length && resp.enable_stock == 1) {
                $wrap.find('.row_qty_available_text').text(resp.formatted);
                if (resp.unit) {
                    $wrap.find('.row_unit_text').text(resp.unit);
                }
            }
            //Update qty validation rule (max-qty) so cashier can't oversell from the chosen location
            var $qty = tr.find('input.pos_quantity');
            if ($qty.length) {
                var newMax = parseFloat(resp.qty_available);
                if (isNaN(newMax)) { newMax = 0; }
                var unit = resp.unit || $qty.closest('tr').find('.row_unit_text').text() || '';
                //Build new validation message: "Quantity <formatted> <unit> available"
                //Replace the old "<number> <unit>" segment in the cached template with new values.
                var tpl = $qty.attr('data-msg_max_default') || $qty.data('msg_max_default') || '';
                var newMsg;
                if (tpl) {
                    //Match a number (with optional decimal/thousand separators) followed by an optional unit word.
                    newMsg = String(tpl).replace(/[\d][\d.,]*(?:\s+\S+)?/, resp.formatted + ' ' + unit);
                } else {
                    newMsg = 'Quantity ' + resp.formatted + ' ' + unit + ' available';
                }
                //Persist the rebuilt template so subsequent location switches keep working.
                $qty.attr('data-msg_max_default', newMsg);

                //Update DOM attrs (data + attr) so any re-parse picks up new values
                $qty.attr('data-rule-max-value', newMax);
                $qty.attr('data-msg-max-value', newMsg);
                $qty.attr('data-qty_available', newMax);
                $qty.removeData('rule-max-value');
                $qty.removeData('msg-max-value');
                $qty.removeData('qty_available');

                //jQuery Validate caches rules at parse time. Force-refresh this field's rules.
                try {
                    var $form = $qty.closest('form');
                    if ($form.length && $.fn.validate) {
                        var validator = $form.data('validator') || $form.validate();
                        if (validator) {
                            //Clear cached static rules for this element so getRules() rebuilds from data-rule-* attrs
                            var elName = $qty.attr('name');
                            if (validator.settings && validator.settings.rules && elName && validator.settings.rules[elName]) {
                                if (validator.settings.rules[elName]['max-value'] !== undefined) {
                                    delete validator.settings.rules[elName]['max-value'];
                                }
                            }
                            //Add updated rule + message
                            $qty.rules('add', {
                                'max-value': newMax,
                                messages: { 'max-value': newMsg }
                            });
                        }
                    }
                } catch (e) { /* validator not initialized yet — DOM attrs above will be used on next parse */ }

                //Re-validate the field & re-trigger qty change so subtotal/stock checks recompute
                $qty.trigger('change');
                if ($qty.valid && typeof $qty.valid === 'function') {
                    $qty.valid();
                }
            }
        });
    });

    //Per-row price group: when changed, refetch unit price for this row only.
    $('table#pos_table').on('change', 'select.row_price_group', function() {
        var $select = $(this);
        var tr = $select.closest('tr.product_row');
        var variation_id = $select.data('variation_id');
        var price_group = $select.val();
        if (!variation_id) { return; }

        var customer_id = $('select#customer_id').val();
        //Choose effective location: per-row location dropdown if present, else header location
        var $rowLoc = tr.find('select.row_line_location');
        var location_id = ($rowLoc.length && $rowLoc.val()) ? $rowLoc.val() : $('input#location_id').val();
        var is_direct_sell = ($('input[name="is_direct_sale"]').length > 0 && $('input[name="is_direct_sale"]').val() == 1);

        $.ajax({
            method: 'GET',
            url: '/sells/pos/get_product_row/' + variation_id + '/' + location_id,
            data: {
                product_row: 0,
                customer_id: customer_id,
                is_direct_sell: is_direct_sell,
                price_group: price_group,
                quantity: 1,
                line_location_id: location_id
            },
            dataType: 'json',
            success: function(result) {
                if (!result || !result.success || !result.html_content) { return; }
                var $tmp = $('<div>').append(result.html_content);
                var new_unit_price = $tmp.find('input.pos_unit_price').val();
                var new_unit_price_inc_tax = $tmp.find('input.row_unit_price_inc_tax').val();
                if (typeof new_unit_price !== 'undefined') {
                    var $up = tr.find('input.pos_unit_price');
                    $up.val(new_unit_price);
                    if (new_unit_price_inc_tax) {
                        tr.find('input.row_unit_price_inc_tax').val(new_unit_price_inc_tax);
                    }
                    $up.change();
                }
            }
        });
    });

    $('table#pos_table').on('change', 'select.sub_unit', function() {
        var tr = $(this).closest('tr');
        var base_unit_selling_price = tr.find('input.hidden_base_unit_sell_price').val();
        var selected_option = $(this).find(':selected');

        var multiplier = parseFloat(selected_option.data('multiplier'));

        var allow_decimal = parseInt(selected_option.data('allow_decimal'));

        tr.find('input.base_unit_multiplier').val(multiplier);

        // PosInnova: prefer the per-unit custom selling price configured on
        // the product (excl. tax) when one is set for this unit; the
        // input.pos_unit_price `change` handler will recompute the inc-tax
        // field from the tax-rate dropdown. Fall back to base * multiplier
        // when no override exists for the chosen unit.
        var custom_price_exc_tax = parseFloat(selected_option.data('custom_price_exc_tax')) || 0;
        var unit_sp;
        if (custom_price_exc_tax > 0) {
            unit_sp = custom_price_exc_tax;
        } else {
            unit_sp = base_unit_selling_price * multiplier;
        }

        var sp_element = tr.find('input.pos_unit_price');
        __write_number(sp_element, unit_sp);

        sp_element.change();

        var qty_element = tr.find('input.pos_quantity');
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
        var abs_digit = true;
        if (allow_decimal) {
            abs_digit = false;
        }
        qty_element.rules('add', {
            abs_digit: abs_digit,
        });

        if (base_max_avlbl) {
            var max_avlbl = parseFloat(base_max_avlbl) / multiplier;
            var formated_max_avlbl = __number_f(max_avlbl);
            var unit_name = selected_option.data('unit_name');
            var max_err_msg = __translate(error_msg_line, {
                max_val: formated_max_avlbl,
                unit_name: unit_name,
            });
            qty_element.attr('data-rule-max-value', max_avlbl);
            qty_element.attr('data-msg-max-value', max_err_msg);
            qty_element.rules('add', {
                'max-value': max_avlbl,
                messages: {
                    'max-value': max_err_msg,
                },
            });
            qty_element.trigger('change');
        }
        adjustComboQty(tr);
    });

    //Confirmation before page load.
    window.onbeforeunload = function() {
        if($('form#edit_pos_sell_form').length == 0){
            if($('table#pos_table tbody tr').length > 0) {
                return LANG.sure;
            } else {
                return null;
            }
        }
    }
    $(window).resize(function() {
        var win_height = $(window).height();
        div_height = __calculate_amount('percentage', 63, win_height);
        // $('div.pos_product_div').css('min-height', div_height + 'px');
        // $('div.pos_product_div').css('max-height', div_height + 'px');
    });

    //Used for weighing scale barcode
    $('#weighing_scale_modal').on('shown.bs.modal', function (e) {

        //Attach the scan event
        onScan.attachTo(document, {
            suffixKeyCodes: [13], // enter-key expected at the end of a scan
            reactToPaste: true, // Compatibility to built-in scanners in paste-mode (as opposed to keyboard-mode)
            onScan: function(sCode, iQty) {
                console.log('Scanned: ' + iQty + 'x ' + sCode); 
                $('input#weighing_scale_barcode').val(sCode);
                $('button#weighing_scale_submit').trigger('click');
            },
            onScanError: function(oDebug) {
                console.log(oDebug); 
            },
            minLength: 2
            // onKeyDetect: function(iKeyCode){ // output all potentially relevant key events - great for debugging!
            //     console.log('Pressed: ' + iKeyCode);
            // }
        });

        $('input#weighing_scale_barcode').focus();
    });

    $('#weighing_scale_modal').on('hide.bs.modal', function (e) {
        //Detach from the document once modal is closed.
        onScan.detachFrom(document);
    });

    $('button#weighing_scale_submit').click(function(){

        var price_group = '';
        if ($('#price_group').length > 0) {
            price_group = $('#price_group').val();
        }

        if($('#weighing_scale_barcode').val().length > 0){
            pos_product_row(null, null, $('#weighing_scale_barcode').val());
            $('#weighing_scale_modal').modal('hide');
            $('input#weighing_scale_barcode').val('');
        } else{
            $('input#weighing_scale_barcode').focus();
        }
    });

    $('#show_featured_products').click( function(){
        if (!$('#featured_products_box').is(':visible')) {
            $('#featured_products_box').fadeIn();
        } else {
            $('#featured_products_box').fadeOut();
        }
    });
    validate_discount_field();
    set_payment_type_dropdown();
    if ($('#__is_mobile').length) {
        $('.pos_form_totals').css('margin-bottom', $('.pos-form-actions').height() - 30);
    }

    setInterval(function () {
        if ($('span.curr_datetime').length) {
            $('span.curr_datetime').html(__current_datetime());
        }
    }, 60000);

    set_search_fields();
});

function set_payment_type_dropdown() {
    var payment_settings = $('#location_id').data('default_payment_accounts');
    payment_settings = payment_settings ? payment_settings : [];
    enabled_payment_types = [];
    for (var key in payment_settings) {
        if (payment_settings[key] && payment_settings[key]['is_enabled']) {
            enabled_payment_types.push(key);
        }
    }
    if (enabled_payment_types.length) {
        $(".payment_types_dropdown > option").each(function() {
            //skip if advance
            if ($(this).val() && $(this).val() != 'advance') {
                if (enabled_payment_types.indexOf($(this).val()) != -1) {
                    $(this).removeClass('hide');
                } else {
                    $(this).addClass('hide');
                }
            }
        });
    }
}

function get_featured_products() {
    var location_id = $('#location_id').val();
    if (location_id && $('#featured_products_box').length > 0) {
        $.ajax({
            method: 'GET',
            url: '/sells/pos/get-featured-products/' + location_id,
            dataType: 'html',
            success: function(result) {
                if (result) {
                    $('#feature_product_div').removeClass('hide');
                    $('#featured_products_box').html(result);
                } else {
                    $('#feature_product_div').addClass('hide');
                    $('#featured_products_box').html('');
                }
            },
        });
    } else {
        $('#feature_product_div').addClass('hide');
        $('#featured_products_box').html('');
    }
}

function get_product_suggestion_list(category_id, brand_id, location_id, url = null, is_enabled_stock = null, repair_model_id = null) {
    if($('div#product_list_body').length == 0) {
        return false;
    }

    if (url == null) {
        url = '/sells/pos/get-product-suggestion';
    }
    $('#suggestion_page_loader').fadeIn(700);
    var page = $('input#suggestion_page').val();
    if (page == 1) {
        $('div#product_list_body').html('');
    }
    if ($('div#product_list_body').find('input#no_products_found').length > 0) {
        $('#suggestion_page_loader').fadeOut(700);
        return false;
    }
    $.ajax({
        method: 'GET',
        url: url,
        data: {
            category_id: category_id,
            brand_id: brand_id,
            location_id: location_id,
            page: page,
            is_enabled_stock: is_enabled_stock,
            repair_model_id: repair_model_id,
            multi_location_search: $('#multi_location_sale_enabled').length ? 1 : 0
        },
        dataType: 'html',
        success: function(result) {
            $('div#product_list_body').append(result);
            $('#suggestion_page_loader').fadeOut(700);
        },
    });
}

//Get recent transactions
function get_recent_transactions(status, element_obj) {
    if (element_obj.length == 0) {
        return false;
    }
    var transaction_sub_type = $("#transaction_sub_type").val();
    $.ajax({
        method: 'GET',
        url: '/sells/pos/get-recent-transactions',
        data: { status: status , transaction_sub_type: transaction_sub_type},
        dataType: 'html',
        success: function(result) {
            element_obj.html(result);
            __currency_convert_recursively(element_obj);
        },
    });
}

//variation_id is null when weighing_scale_barcode is used.

/**
 * PosInnova: mint a fresh idempotency key for the next checkout.
 *
 * The key identifies one checkout attempt, not one request. It is kept across
 * a FAILED submit on purpose: if the first attempt never created a sale the
 * retry proceeds normally, and if it did create one but the response was lost,
 * the retry is recognised and the existing sale is returned instead of a
 * duplicate. It is replaced only once a sale has actually been recorded.
 */
function pos_new_idempotency_key() {
    var key;
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        key = window.crypto.randomUUID();
    } else {
        key = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : ((r & 0x3) | 0x8)).toString(16);
        });
    }
    $('input#idempotency_key').val(key);

    return key;
}

/**
 * PosInnova: reserve the next product row index.
 *
 * The index is what becomes the POST field name (products[N][...]), so it must
 * be unique across the cart. Reserving is synchronous - the counter is bumped
 * the moment an index is handed out - so two products added inside one AJAX
 * round-trip can never receive the same index.
 *
 * @returns {number} an index no other row holds
 */
function pos_reserve_product_row_index() {
    var current = parseInt($('input#product_row_count').val());
    if (isNaN(current)) {
        current = 0;
    }
    $('input#product_row_count').val(current + 1);

    return current;
}

/**
 * PosInnova: stamp a cart row with an index.
 *
 * Rewrites every products[...] field name in the row, plus the per-row modal id
 * and the data-row_index attributes that other handlers match rows on. Only the
 * FIRST bracket is replaced, so nested names such as
 * products[3][combo][1][quantity] keep their inner indexes.
 *
 * @param {object} $row      the <tr> to stamp
 * @param {number} new_index the index to give it
 */
function pos_set_product_row_index($row, new_index) {
    if (!$row || !$row.length) {
        return;
    }

    $row.find('[name^="products["]').each(function () {
        var name = $(this).attr('name');
        if (name) {
            $(this).attr('name', name.replace(/^products\[\d+\]/, 'products[' + new_index + ']'));
        }
    });

    //Keep the per-row price modal addressable, and unique.
    $row.find('.row_edit_product_price_model').attr('id', 'row_edit_product_price_modal_' + new_index);
    $row.find('[data-target^="#row_edit_product_price_modal_"]')
        .attr('data-target', '#row_edit_product_price_modal_' + new_index);

    //Set the attribute AND jQuery's data cache - .attr() alone leaves a stale
    //value behind for anything that already called .data('row_index').
    $row.attr('data-row_index', new_index).data('row_index', new_index);
    $row.find('[data-row_index]').each(function () {
        $(this).attr('data-row_index', new_index).data('row_index', new_index);
    });
}

/**
 * PosInnova: renumber every cart row 0..n-1 in display order.
 *
 * Called immediately before the form is serialised. Even if a duplicate index
 * were to slip through, this guarantees the server receives one products[N]
 * entry per visible row - PHP silently keeps only the last value of a repeated
 * key, which would drop a line from the sale without any error.
 *
 * Also records the row count so the server can verify nothing was lost in
 * transit (see expected_product_rows).
 *
 * @returns {number} the number of rows submitted
 */
function pos_reindex_product_rows() {
    var $rows = $('table#pos_table tbody').find('tr.product_row');

    $rows.each(function (index) {
        pos_set_product_row_index($(this), index);
    });

    $('input#expected_product_rows').val($rows.length);

    return $rows.length;
}

/**
 * Common function to insert product row into POS table
 * @param {object} result - The result object containing html_content and other data
 */
function pos_insert_product_row(result) {
    $('table#pos_table tbody')
        .append(result.html_content)
        .find('input.pos_quantity');
    var this_row = $('table#pos_table tbody')
        .find('tr')
        .last();
    //PosInnova: the index the server baked into this row was derived from the
    //counter as it stood when the request was SENT, so two products added
    //within one round-trip come back stamped identically. Re-stamp locally with
    //a reserved index instead of trusting it.
    pos_set_product_row_index(this_row, pos_reserve_product_row_index());
    pos_each_row(this_row);

    //For initial discount if present
    var line_total = __read_number(this_row.find('input.pos_line_total'));
    this_row.find('span.pos_line_total_text').text(line_total);

    pos_total_row();

    //Check if multipler is present then multiply it when a new row is added.
    if(__getUnitMultiplier(this_row) > 1){
        this_row.find('select.sub_unit').trigger('change');
    } else {
        // PosInnova: even when multiplier is 1 (base unit), the configured
        // per-unit selling price override must take effect on initial insert.
        var $sub_sel = this_row.find('select.sub_unit');
        if ($sub_sel.length) {
            var $opt = $sub_sel.find(':selected');
            var override = parseFloat($opt.data('custom_price_exc_tax')) || 0;
            if (override > 0) {
                $sub_sel.trigger('change');
            }
        }
    }

    if (result.enable_sr_no == '1') {
        var new_row = $('table#pos_table tbody')
            .find('tr')
            .last();
        new_row.find('.row_edit_product_price_model').modal('show');
    }

    round_row_to_iraqi_dinnar(this_row);
    __currency_convert_recursively(this_row);

    pos_focus_search_after_add();

    //Used in restaurant module
    if (result.html_modifier) {
        $('table#pos_table tbody')
            .find('tr')
            .last()
            .find('td:first')
            .append(result.html_modifier);
    }

    //scroll bottom of items list
    $(".pos_product_div").animate({ scrollTop: $('.pos_product_div').prop("scrollHeight")}, 1000);
}

//PosInnova: every input that shapes a product search, in one place. Used by the
//search box as you type AND by pos_commit_search_term() (Enter / scanner), so the
//two can never drift apart. Everything is read at call time.
function pos_search_request_params(term) {
    var price_group = '';
    var search_fields = [];
    $('.search_fields:checked').each(function(i) {
        search_fields[i] = $(this).val();
    });

    if ($('#price_group').length > 0) {
        price_group = $('#price_group').val();
    }

    //If default price group present
    if ($('#default_price_group').length > 0 && price_group === '') {
        price_group = $('#default_price_group').val();
    }

    //If types of service selected give more priority
    if ($('#types_of_service_price_group').length > 0 &&
        $('#types_of_service_price_group').val()) {
        price_group = $('#types_of_service_price_group').val();
    }

    var is_draft = false;
    if ($('#status') && ($('#status').val() == 'quotation' ||
        $('#status').val() == 'draft')) {
        is_draft = true;
    }

    return {
        price_group: price_group,
        location_id: $('input#location_id').val(),
        term: term,
        not_for_selling: 0,
        search_fields: search_fields,
        auto_add_single: true,
        auto_add_only_exact_sku: $('#pos_disable_auto_add').val() == 1 ? 1 : 0,
        product_row: $('input#product_row_count').val(),
        customer_id: $('select#customer_id').val(),
        is_direct_sell: $('input[name="is_direct_sale"]').length > 0 &&
            $('input[name="is_direct_sale"]').val() == 1,
        is_serial_no: $('input[name="is_serial_no"]').length > 0 &&
            $('input[name="is_serial_no"]').val() == 1,
        is_sales_order: $('#sale_type').length && $('#sale_type').val() == 'sales_order' ? true : false,
        disable_qty_alert: $('#disable_qty_alert').length ? true : false,
        is_draft: is_draft,
        multi_location_search: $('#multi_location_sale_enabled').length ? 1 : 0
    };
}

//PosInnova: Enter in the search box commits what is in it - which is what a
//barcode scanner does after every code.
//
//The text leaves the box at once, so the next scan starts in an empty box. It
//used to stay until the server answered (~1-2s), so a second scan landed on the
//end of the first - "ACICLOACICLO" - matched nothing, and its keystrokes aborted
//the first scan's request: BOTH items were lost.
//
//The request is deliberately NOT kept in __posSearchInFlight, so nothing typed
//afterwards can abort it, and its line goes through pos_add_product_row_from_data(),
//which decides merge-or-insert when the line arrives - three scans of the same box
//in quick succession end as one line with qty 3.
function pos_commit_search_term(term) {
    var $box = $('#search_product');
    $box.val('');
    try { $box.autocomplete('close'); } catch (e) {}

    //anything still waiting or in flight for this text is now redundant
    clearTimeout(window.__posSearchTimer);
    if (window.__posSearchPendingResponse) {
        window.__posSearchPendingResponse([]);
        window.__posSearchPendingResponse = null;
    }
    if (window.__posSearchInFlight && window.__posSearchInFlight.readyState !== 4) {
        try { window.__posSearchInFlight.abort(); } catch (e) {}
    }

    return $.getJSON('/products/list', pos_search_request_params(term), function(data) {
        if (data && data.auto_add && data.row_data) {
            if (data.row_data.success) {
                pos_add_product_row_from_data(data.row_data);
            } else {
                toastr.error(data.row_data.msg);
                //give the code back so it can be read and retyped - unless the
                //cashier has already moved on to the next one
                if (String($box.val() || '') === '') {
                    $box.val(term).select();
                }
            }
            return;
        }

        var list = (data && (data.products || data)) || [];
        var boxFree = String($box.val() || '') === '';

        //Nothing matches. Say so here: the list's own response handler stays silent
        //when "Disable auto-add" is on (as it is for PMI), so an unknown barcode used
        //to just sit in the box. Hand it back HIGHLIGHTED, so the next scan replaces
        //it instead of being appended to it.
        if (!list.length) {
            toastr.error(LANG.no_products_found + ': ' + term);
            if (boxFree) {
                $box.val(term).select();
            }
            return;
        }

        //Several matches, or one that wasn't auto-added. If the cashier hasn't
        //started on something else, put the text back and let the normal search
        //flow take it from there: one match is selected (lot pre-selection
        //included), several open the list - as it behaved before Enter was handled.
        if (boxFree) {
            $box.val(term).autocomplete('search', term);
        } else {
            toastr.warning(LANG.please_select + ': ' + term);
        }
    });
}

//PosInnova: after a line is added, put the cursor back in the search box - but only
//highlight its text when the box is empty. When it isn't, that text is the NEXT
//code a scanner is typing at this very moment, and highlighting it made the rest
//of the keystrokes overwrite it. (Failure paths still highlight on purpose: the
//rejected code is selected so it can be retyped.)
function pos_focus_search_after_add() {
    if ($('#__is_mobile').length) {
        return;
    }
    var $b = $('input#search_product');
    if (document.activeElement !== $b[0]) {
        $b.focus();
    }
    if (!$b.val()) {
        $b.select();
    }
}

// Helper function to add product row from server data
function pos_add_product_row_from_data(result) {
    if (result.success) {
        var variation_id = result.variation_id;
        var add_new_row = true;
        
        // Check item addition method setting
        var item_addtn_method = 0;
        if ($('#item_addition_method').length) {
            item_addtn_method = $('#item_addition_method').val();
        }
        
        // If item_addtn_method != 0, check for duplicate products
        if (item_addtn_method != 0 && variation_id) {
            var is_added = false;
            // Multi-location sale: when rows have a per-line location selector,
            // only treat rows as duplicates if their selected location matches
            // the row we would otherwise add. Without this, scanning the same
            // product after switching a row's location silently increments the
            // wrong row and the receipt prints the wrong source location.
            var default_location_id = $('input#location_id').val();

            // Search for variation id in each row of pos table
            $('#pos_table tbody')
                .find('tr')
                .each(function() {
                    var row_v_id = $(this).find('.row_variation_id').val();
                    var enable_sr_no = $(this).find('.enable_sr_no').val();
                    var modifiers_exist = false;
                    if ($(this).find('input.modifiers_exist').length > 0) {
                        modifiers_exist = true;
                    }
                    var $row_loc_sel = $(this).find('.row_line_location');
                    var row_loc_matches = true;
                    if ($row_loc_sel.length > 0) {
                        row_loc_matches = (String($row_loc_sel.val()) === String(default_location_id));
                    }

                    if (
                        row_v_id == variation_id &&
                        enable_sr_no !== '1' &&
                        !modifiers_exist &&
                        row_loc_matches &&
                        !is_added
                    ) {
                        add_new_row = false;
                        is_added = true;
                        
                        // Increment product quantity
                        var qty_element = $(this).find('.pos_quantity');
                        var qty = __read_number(qty_element);
                        __write_number(qty_element, qty + 1);
                        qty_element.change();
                        
                        round_row_to_iraqi_dinnar($(this));
                        
                        pos_focus_search_after_add();
                    }
            });
        }
        
        // Add new row if not a duplicate
        if (add_new_row) {
            pos_insert_product_row(result);
        }
    } else {
        toastr.error(result.msg);
        if (!$('#__is_mobile').length) {
            $('input#search_product')
                .focus()
                .select();
        }
    }
}

function pos_product_row(variation_id = null, purchase_line_id = null, weighing_scale_barcode = null, quantity = 1) {

    //Get item addition method
    var item_addtn_method = 0;
    var add_via_ajax = true;

    if (variation_id != null && $('#item_addition_method').length) {
        item_addtn_method = $('#item_addition_method').val();
    }

    if (item_addtn_method == 0) {
        add_via_ajax = true;
    } else {
        var is_added = false;
        // Multi-location sale: only treat as duplicate when the existing row's
        // selected per-line location also matches the default location used
        // for new rows. See pos_add_product_row_from_data() for rationale.
        var default_location_id = $('input#location_id').val();

        //Search for variation id in each row of pos table
        $('#pos_table tbody')
            .find('tr')
            .each(function() {
                var row_v_id = $(this)
                    .find('.row_variation_id')
                    .val();
                var enable_sr_no = $(this)
                    .find('.enable_sr_no')
                    .val();
                var modifiers_exist = false;
                if ($(this).find('input.modifiers_exist').length > 0) {
                    modifiers_exist = true;
                }
                var $row_loc_sel = $(this).find('.row_line_location');
                var row_loc_matches = true;
                if ($row_loc_sel.length > 0) {
                    row_loc_matches = (String($row_loc_sel.val()) === String(default_location_id));
                }

                if (
                    row_v_id == variation_id &&
                    enable_sr_no !== '1' &&
                    !modifiers_exist &&
                    row_loc_matches &&
                    !is_added
                ) {
                    add_via_ajax = false;
                    is_added = true;

                    //Increment product quantity
                    qty_element = $(this).find('.pos_quantity');
                    var qty = __read_number(qty_element);
                    __write_number(qty_element, qty + 1);
                    qty_element.change();

                    round_row_to_iraqi_dinnar($(this));

                    pos_focus_search_after_add();
                }
        });
    }

    if (add_via_ajax) {
        var product_row = $('input#product_row_count').val();
        var location_id = $('input#location_id').val();
        var customer_id = $('select#customer_id').val();
        var is_direct_sell = false;
        if (
            $('input[name="is_direct_sale"]').length > 0 &&
            $('input[name="is_direct_sale"]').val() == 1
        ) {
            is_direct_sell = true;
        }

        var disable_qty_alert = false;

        if ($('#disable_qty_alert').length) {
            disable_qty_alert = true;
        }

        var is_sales_order = $('#sale_type').length && $('#sale_type').val() == 'sales_order' ? true : false;

        var price_group = '';
        if ($('#price_group').length > 0) {
            price_group = parseInt($('#price_group').val());
        }

        //If default price group present
        if ($('#default_price_group').length > 0 && 
            price_group === '') {
            price_group = $('#default_price_group').val();
        }

        //If types of service selected give more priority
        if ($('#types_of_service_price_group').length > 0 && 
            $('#types_of_service_price_group').val()) {
            price_group = $('#types_of_service_price_group').val();
        }

        var is_draft=false;
        if($('#status') && ($('#status').val()=='quotation' || 
        $('#status').val()=='draft')) {
            is_draft=true;
        }

        var is_serial_no = false;

        if (
            $('input[name="is_serial_no"]').length > 0 &&
            $('input[name="is_serial_no"]').val() == 1
        ) {
            is_serial_no = true;
        }

        var __doInsertRow = function(chosen_location_id) {
            //PosInnova: was async:false which froze the entire UI thread for 200-800ms
            //per click on XAMPP. Switched to async so the browser stays responsive.
            //Nothing in this function relies on the AJAX completing before returning.
            $.ajax({
                method: 'GET',
                url: '/sells/pos/get_product_row/' + variation_id + '/' + chosen_location_id,
                async: true,
                data: {
                    product_row: product_row,
                    customer_id: customer_id,
                    is_direct_sell: is_direct_sell,
                    is_serial_no: is_serial_no,
                    price_group: price_group,
                    purchase_line_id: purchase_line_id,
                    weighing_scale_barcode: weighing_scale_barcode,
                    quantity: quantity,
                    is_sales_order: is_sales_order,
                    disable_qty_alert: disable_qty_alert,
                    is_draft: is_draft,
                    line_location_id: chosen_location_id
                },
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        //PosInnova: decide merge-or-insert NOW, when the line arrives,
                        //not when the click happened. The duplicate check above runs
                        //before the request, so two clicks on one tile inside a
                        //round-trip both saw "not in the cart yet" and the cashier got
                        //two lines of qty 1. pos_add_product_row_from_data() re-checks
                        //against the cart as it is now (the scan path already did).
                        //Only plain qty-1 adds at the sale's own location take this
                        //route: weighing-scale, lot-specific and other-location lines
                        //keep the original insert.
                        if (variation_id && !weighing_scale_barcode && !purchase_line_id &&
                            quantity == 1 && String(chosen_location_id) === String(location_id)) {
                            result.variation_id = result.variation_id || variation_id;
                            pos_add_product_row_from_data(result);
                        } else {
                            pos_insert_product_row(result);
                        }
                    } else {
                        toastr.error(result.msg);
                        if (!$('#__is_mobile').length) {
                            $('input#search_product')
                                .focus()
                                .select();
                        }
                    }
                },
            });
        };

        //Multi-location sale: if enabled and product available in 2+ locations, ask cashier to pick
        var multi_loc_enabled = $('#multi_location_sale_enabled').length > 0;
        var allow_oversell = $('#is_overselling_allowed').length > 0;

        if (multi_loc_enabled && variation_id) {
            $.getJSON('/sells/pos/get-variation-locations/' + variation_id, function(resp) {
                if (!resp || !resp.success) {
                    __doInsertRow(location_id);
                    return;
                }
                //If product doesn't track stock, just use header location
                if (resp.enable_stock != 1) {
                    __doInsertRow(location_id);
                    return;
                }
                //If only one permitted location, use it directly
                if (resp.locations.length === 1) {
                    __doInsertRow(resp.locations[0].location_id);
                    return;
                }
                var available = resp.locations.filter(function(l) { return l.qty_available > 0; });
                //Auto-pick if exactly one location has stock and overselling is off
                if (available.length === 1 && !allow_oversell) {
                    __doInsertRow(available[0].location_id);
                    return;
                }
                //Otherwise always show picker modal so cashier can choose.
                //If overselling is off, 0-stock options are disabled.
                var $list = $('#select_sale_location_list');
                $('#select_sale_location_product_name').text(resp.product_name);
                $list.empty();
                if (available.length === 0 && !allow_oversell) {
                    $list.append(
                        '<div class="alert alert-warning" style="margin-bottom:8px;">'
                        + '<i class="fa fa-exclamation-triangle"></i> '
                        + (resp.product_name + ' — 0 in stock at all locations. Enable "Allow overselling" in POS settings to sell it.')
                        + '</div>'
                    );
                }
                resp.locations.forEach(function(l) {
                    var disabled = (l.qty_available <= 0 && !allow_oversell);
                    var $btn = $('<button type="button" class="list-group-item"></button>')
                        .css('cursor', disabled ? 'not-allowed' : 'pointer')
                        .css('opacity', disabled ? '0.5' : '1')
                        .html('<strong>' + l.name + '</strong> '
                            + '<span class="pull-right label label-' + (l.qty_available > 0 ? 'success' : 'danger') + '">'
                            + l.formatted + ' ' + (resp.unit || '') + '</span>');
                    if (!disabled) {
                        $btn.on('click', function() {
                            $('#select_sale_location_modal').modal('hide');
                            __doInsertRow(l.location_id);
                        });
                    } else {
                        $btn.prop('disabled', true);
                    }
                    $list.append($btn);
                });
                $('#select_sale_location_modal').modal('show');
            }).fail(function() {
                __doInsertRow(location_id);
            });
        } else {
            __doInsertRow(location_id);
        }
    }
}

//Update values for each row
function pos_each_row(row_obj) {
    var unit_price = __read_number(row_obj.find('input.pos_unit_price'));

    var discounted_unit_price = calculate_discounted_unit_price(row_obj);
    var tax_rate = row_obj
        .find('select.tax_id')
        .find(':selected')
        .data('rate');

    var unit_price_inc_tax =
        discounted_unit_price + __calculate_amount('percentage', tax_rate, discounted_unit_price);
    __write_number(row_obj.find('input.pos_unit_price_inc_tax'), unit_price_inc_tax);

    var discount = __read_number(row_obj.find('input.row_discount_amount'));

    if (discount > 0) {
        var qty = __read_number(row_obj.find('input.pos_quantity'));
        var line_total = qty * unit_price_inc_tax;
        __write_number(row_obj.find('input.pos_line_total'), line_total);
    }

    //var unit_price_inc_tax = __read_number(row_obj.find('input.pos_unit_price_inc_tax'));

    __write_number(row_obj.find('input.item_tax'), unit_price_inc_tax - discounted_unit_price);
}

function pos_total_row() {
    var total_quantity = 0;
    var price_total = get_subtotal();
    $('table#pos_table tbody tr').each(function() {
        total_quantity = total_quantity + __read_number($(this).find('input.pos_quantity'));
    });

    //updating shipping charges
    $('span#shipping_charges_amount').text(
        __currency_trans_from_en(__read_number($('input#shipping_charges_modal')), false)
    );

    $('span.total_quantity').each(function() {
        $(this).html(__number_f(total_quantity));
    });

    //$('span.unit_price_total').html(unit_price_total);
    $('span.price_total').html(__currency_trans_from_en(price_total, false));
    calculate_billing_details(price_total);

    if (
        $('input[name="is_serial_no"]').length > 0 &&
        $('input[name="is_serial_no"]').val() == 1
    ) {
        update_serial_no();
    }
    // store on any update
    saveFormDataToLocalStorage();

}

function get_subtotal() {
    var price_total = 0;

    $('table#pos_table tbody tr').each(function() {
        price_total = price_total + __read_number($(this).find('input.pos_line_total'));
    });

    //Go through the modifier prices.
    $('input.modifiers_price').each(function() {
        var modifier_price = __read_number($(this));
        var modifier_quantity = $(this).closest('.product_modifier').find('.modifiers_quantity').val();
        var modifier_subtotal = modifier_price * modifier_quantity;
        price_total = price_total + modifier_subtotal;
    });

    return price_total;
}

function calculate_billing_details(price_total) {
    var discount = pos_discount(price_total);
    if ($('#reward_point_enabled').length) {
        total_customer_reward = $('#rp_redeemed_amount').val();
        discount = parseFloat(discount) + parseFloat(total_customer_reward);

        if ($('input[name="is_direct_sale"]').length <= 0) {
            $('span#total_discount').text(__currency_trans_from_en(discount, false));
        }
    }

    var order_tax = pos_order_tax(price_total, discount);

    //Add shipping charges.
    var shipping_charges = __read_number($('input#shipping_charges'));

    var additional_expense = 0;
    //calculate additional expenses
    if ($('input#additional_expense_value_1').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_1'));
    }
    if ($('input#additional_expense_value_2').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_2'))
    }
    if ($('input#additional_expense_value_3').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_3'))
    }
    if ($('input#additional_expense_value_4').length > 0) {
        additional_expense += __read_number($('input#additional_expense_value_4'))
    }

    //Add packaging charge
    var packing_charge = 0;
    if ($('#types_of_service_id').length > 0 && 
            $('#types_of_service_id').val()) {
        packing_charge = __calculate_amount($('#packing_charge_type').val(), 
            __read_number($('input#packing_charge')), price_total);

        $('#packing_charge_text').text(__currency_trans_from_en(packing_charge, false));
    }

    var total_payable = price_total + order_tax - discount + shipping_charges + packing_charge + additional_expense;

    var rounding_multiple = $('#amount_rounding_method').val() ? parseFloat($('#amount_rounding_method').val()) : 0;
    var round_off_data = __round(total_payable, rounding_multiple);
    var total_payable_rounded = round_off_data.number;

    var round_off_amount = round_off_data.diff;
    if (round_off_amount != 0) {
        $('span#round_off_text').text(__currency_trans_from_en(round_off_amount, false));
    } else {
        $('span#round_off_text').text(0);
    }
    $('input#round_off_amount').val(round_off_amount);

    __write_number($('input#final_total_input'), total_payable_rounded);
    var curr_exchange_rate = 1;
    if ($('#exchange_rate').length > 0 && $('#exchange_rate').val()) {
        curr_exchange_rate = __read_number($('#exchange_rate'));
    }
    var shown_total = total_payable_rounded * curr_exchange_rate;
    $('span#total_payable').text(__currency_trans_from_en(shown_total, false));

    $('span.total_payable_span').text(__currency_trans_from_en(total_payable_rounded, true));

    //Check if edit form then don't update price.
    //PosInnova: ...unless the sale has no saved payment yet (an opened draft or quotation)
    //and the cashier has not typed an amount: then the payment follows the total like on
    //a new sale. Otherwise a draft of 32,300 cut down to 27,500 kept 32,300 in the cash
    //field and the screen showed 4,800 of "change" that nobody owed.
    var pi_is_edit = $('form#edit_pos_sell_form').length > 0 || $('form#edit_sell_form').length > 0;
    var pi_first_pay = $('.payment-amount').first();
    var pi_has_saved_payment = $('#payment_rows_div input[name$="[payment_id]"]').filter(function() {
        return $(this).val();
    }).length > 0;
    if (!pi_is_edit || (!pi_has_saved_payment && !pi_first_pay.data('pi-user-typed'))) {
        __write_number(pi_first_pay, total_payable_rounded);
    }

    $(document).trigger('invoice_total_calculated');

    calculate_balance_due();
}

function pos_discount(total_amount) {
    var calculation_type = $('#discount_type').val();
    var calculation_amount = __read_number($('#discount_amount'));

    var discount = __calculate_amount(calculation_type, calculation_amount, total_amount);

    $('span#total_discount').text(__currency_trans_from_en(discount, false));

    return discount;
}

function pos_order_tax(price_total, discount) {
    var tax_rate_id = $('#tax_rate_id').val();
    var calculation_type = 'percentage';
    var calculation_amount = __read_number($('#tax_calculation_amount'));
    var total_amount = price_total - discount;

    if (tax_rate_id) {
        var order_tax = __calculate_amount(calculation_type, calculation_amount, total_amount);
    } else {
        var order_tax = 0;
    }

    $('span#order_tax').text(__currency_trans_from_en(order_tax, false));

    return order_tax;
}

function calculate_balance_due() {
    var total_payable = __read_number($('#final_total_input'));
    var total_paying = 0;
    $('#payment_rows_div')
        .find('.payment-amount')
        .each(function() {
            if (parseFloat($(this).val())) {
                total_paying += __read_number($(this));
            }
        });
    var bal_due = total_payable - total_paying;
    var change_return = 0;

    //change_return
    if (bal_due < 0 || Math.abs(bal_due) < 0.05) {
        __write_number($('input#change_return'), bal_due * -1);
        $('span.change_return_span').text(__currency_trans_from_en(bal_due * -1, true));
        change_return = bal_due * -1;
        bal_due = 0;
    } else {
        __write_number($('input#change_return'), 0);
        $('span.change_return_span').text(__currency_trans_from_en(0, true));
        change_return = 0;
        
    }

    if (change_return !== 0) {
        $('#change_return_payment_data').removeClass('hide');
    } else {
        $('#change_return_payment_data').addClass('hide');
    }

    __write_number($('input#total_paying_input'), total_paying);
    $('span.total_paying').text(__currency_trans_from_en(total_paying, true));

    __write_number($('input#in_balance_due'), bal_due);
    $('span.balance_due').text(__currency_trans_from_en(bal_due, true));

    __highlight(bal_due * -1, $('span.balance_due'));
    __highlight(change_return * -1, $('span.change_return_span'));
    // store payment details
    saveFormDataToLocalStorage();
}

function isValidPosForm() {
    flag = true;
    $('span.error').remove();

    if ($('select#customer_id').val() == null) {
        flag = false;
        error = '<span class="error">' + LANG.required + '</span>';
        $(error).insertAfter($('select#customer_id').parent('div'));
    }

    if ($('#is_custom_customer_name_required').val() == 1) {
        if ($('select#customer_id').val() == getWalkInCustomerId()) {
            if ($.trim($('#walk_in_customer_name').val()) == '') {
                flag = false;
                error = '<span class="error">' + LANG.required + '</span>';
                $(error).insertAfter($('#walk_in_customer_name').parent('div'));
            }
        }
    }

    if ($('tr.product_row').length == 0) {
        flag = false;
        error = '<span class="error">' + LANG.no_products + '</span>';
        $(error).insertAfter($('input#search_product').parent('div'));
    }

    return flag;
}

function reset_pos_form(){

	//If on edit page then redirect to Add POS page
	if($('form#edit_pos_sell_form').length > 0){
		setTimeout(function() {
			window.location = $("input#pos_redirect_url").val();
		}, 4000);
		return true;
	}
	
    //reset all repair defects tags
    if ($("#repair_defects").length > 0) {
        tagify_repair_defects.removeAllTags();
    }

	if(pos_form_obj[0]){
		pos_form_obj[0].reset();
	}
	//PosInnova: the previous sale is recorded - start a new checkout identity.
	//(form.reset() restores the server-rendered value, which is already used.)
	pos_new_idempotency_key();
	if(sell_form[0]){
		sell_form[0].reset();
	}
	set_default_customer();
	set_location();

	$('tr.product_row').remove();
	$('span.total_quantity, span.price_total, span#total_discount, span#order_tax, span#total_payable, span#shipping_charges_amount').text(0);
	$('span.total_payable_span', 'span.total_paying', 'span.balance_due').text(0);

	$('#modal_payment').find('.remove_payment_row').each( function(){
		$(this).closest('.payment_row').remove();
	});

    if ($('#is_credit_sale').length) {
        $('#is_credit_sale').val(0);
    }

	//Reset discount
	__write_number($('input#discount_amount'), $('input#discount_amount').data('default'));
	$('input#discount_type').val($('input#discount_type').data('default'));

	//Reset tax rate
	$('input#tax_rate_id').val($('input#tax_rate_id').data('default'));
	__write_number($('input#tax_calculation_amount'), $('input#tax_calculation_amount').data('default'));

	$('select.payment_types_dropdown').val('cash').trigger('change');
	$('#price_group').trigger('change');

	//Reset shipping
	__write_number($('input#shipping_charges'), $('input#shipping_charges').data('default'));
	$('input#shipping_details').val($('input#shipping_details').data('default'));
    $('input#shipping_address, input#shipping_status, input#delivered_to').val('');
	if($('input#is_recurring').length > 0){
		$('input#is_recurring').iCheck('update');
	};
    if($('input#is_kitchen_order').length > 0){
		$('input#is_kitchen_order').iCheck('update');
	};
    if($('#invoice_layout_id').length > 0){
        $('#invoice_layout_id').trigger('change');
    };
    $('span#round_off_text').text(0);

    //repair module extra  fields reset
    if ($('#repair_device_id').length > 0) {
        $('#repair_device_id').val('').trigger('change');
    }

    //Status is hidden in sales order
    if ($('#status').length > 0 && $('#status').is(":visible")) {
        $('#status').val('').trigger('change');
    }
    if ($('#transaction_date').length > 0) {
        $('#transaction_date').data("DateTimePicker").date(moment());
    }
    if ($('.paid_on').length > 0) {
        $('.paid_on').data("DateTimePicker").date(moment());
    }
    if ($('#commission_agent').length > 0) {
        $('#commission_agent').val('').trigger('change');
    } 

    //reset contact due
    $('.contact_due_text').find('span').text('');
    $('.contact_due_text').addClass('hide');

    $(document).trigger('sell_form_reset');

    // Set global_is_clear_local_storage to true to clear local storage
    global_is_clear_local_storage = true;
    saveFormDataToLocalStorage();
}

function set_default_customer() {
    var default_customer_id = $('#default_customer_id').val();
    var default_customer_name = $('#default_customer_name').val();
    var default_customer_balance = $('#default_customer_balance').val();
    var default_customer_address = $('#default_customer_address').val();
    var exists = default_customer_id ? $('select#customer_id option[value=' + default_customer_id + ']').length : 0;
    if (exists == 0 && default_customer_id) {
        $('select#customer_id').append(
            $('<option>', { value: default_customer_id, text: default_customer_name })
        );
    }
    $('#advance_balance_text').text(__currency_trans_from_en(default_customer_balance), true);
    $('#advance_balance').val(default_customer_balance);
    $('#shipping_address_modal').val(default_customer_address);
    if (default_customer_address) {
        $('#shipping_address').val(default_customer_address);
    }
    $('select#customer_id')
        .val(default_customer_id)
        .trigger('change');

    if ($('#default_selling_price_group').length) {
        $('#price_group').val($('#default_selling_price_group').val());
        $('#price_group').change();
    }

    //initialize tags input (tagify)
    if ($("textarea#repair_defects").length > 0 && !customer_set) {
        let suggestions = [];
        if ($("input#pos_repair_defects_suggestion").length > 0 && $("input#pos_repair_defects_suggestion").val().length > 2) {
            suggestions = JSON.parse($("input#pos_repair_defects_suggestion").val());    
        }
        let repair_defects = document.querySelector('textarea#repair_defects');
        tagify_repair_defects = new Tagify(repair_defects, {
                  whitelist: suggestions,
                  maxTags: 100,
                  dropdown: {
                    maxItems: 100,           // <- mixumum allowed rendered suggestions
                    classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
                    enabled: 0,             // <- show suggestions on focus
                    closeOnSelect: false    // <- do not hide the suggestions dropdown once an item has been selected
                  }
                });
    }

    customer_set = true;
}

//Set the location and initialize printer
function set_location() {
    if ($('select#select_location_id').length == 1) {
        $('input#location_id').val($('select#select_location_id').val());
        $('input#location_id').data(
            'receipt_printer_type',
            $('select#select_location_id')
                .find(':selected')
                .data('receipt_printer_type')
        );
        $('input#location_id').data(
            'default_payment_accounts',
            $('select#select_location_id')
                .find(':selected')
                .data('default_payment_accounts')
        );

        $('input#location_id').attr(
            'data-default_price_group',
            $('select#select_location_id')
                .find(':selected')
                .data('default_price_group')
        );
    }

    if ($('input#location_id').val()) {
        $('input#search_product')
            .prop('disabled', false);
        if (!$('#__is_mobile').length) {
            $('input#search_product').focus();
        }
    } else {
        $('input#search_product').prop('disabled', true);
    }

    initialize_printer();
}

function initialize_printer() {
    if ($('input#location_id').data('receipt_printer_type') == 'printer') {
        initializeSocket();
    }
}

$('body').on('click', 'label', function(e) {
    var field_id = $(this).attr('for');
    if (field_id) {
        if ($('#' + field_id).hasClass('select2')) {
            $('#' + field_id).select2('open');
            return false;
        }
    }
});

$('body').on('focus', 'select', function(e) {
    var field_id = $(this).attr('id');
    if (field_id) {
        if ($('#' + field_id).hasClass('select2')) {
            $('#' + field_id).select2('open');
            return false;
        }
    }
});

function round_row_to_iraqi_dinnar(row) {
    if (iraqi_selling_price_adjustment) {
        var element = row.find('input.pos_unit_price_inc_tax');
        var unit_price = round_to_iraqi_dinnar(__read_number(element));
        __write_number(element, unit_price);
        element.change();
    }
}

function pos_print(receipt) {
    //If printer type then connect with websocket
    if (receipt.print_type == 'printer') {
        var content = receipt;
        content.type = 'print-receipt';

        //Check if ready or not, then print.
        if (socket != null && socket.readyState == 1) {
            socket.send(JSON.stringify(content));
        } else {
            initializeSocket();
            setTimeout(function() {
                socket.send(JSON.stringify(content));
            }, 700);
        }

    } else if (receipt.html_content != '') {
        var title = document.title;
        if (typeof receipt.print_title != 'undefined') {
            document.title = receipt.print_title;
        }

        //If printer type browser then print content
        $('#receipt_section').html(receipt.html_content);
        __currency_convert_recursively($('#receipt_section'));
        __print_receipt('receipt_section');

        setTimeout(function() {
            document.title = title;
        }, 1200);
    }
}

function calculate_discounted_unit_price(row) {
    var this_unit_price = __read_number(row.find('input.pos_unit_price'));
    var row_discounted_unit_price = this_unit_price;
    var row_discount_type = row.find('select.row_discount_type').val();
    var row_discount_amount = __read_number(row.find('input.row_discount_amount'));
    if (row_discount_amount) {
        if (row_discount_type == 'fixed') {
            row_discounted_unit_price = this_unit_price - row_discount_amount;
        } else {
            row_discounted_unit_price = __substract_percent(this_unit_price, row_discount_amount);
        }
    }

    return row_discounted_unit_price;
}

function get_unit_price_from_discounted_unit_price(row, discounted_unit_price) {
    var this_unit_price = discounted_unit_price;
    var row_discount_type = row.find('select.row_discount_type').val();
    var row_discount_amount = __read_number(row.find('input.row_discount_amount'));
    if (row_discount_amount) {
        if (row_discount_type == 'fixed') {
            this_unit_price = discounted_unit_price + row_discount_amount;
        } else {
            this_unit_price = __get_principle(discounted_unit_price, row_discount_amount, true);
        }
    }

    return this_unit_price;
}

//Update quantity if line subtotal changes
$('table#pos_table tbody').on('change', 'input.pos_line_total', function() {

    var subtotal = __read_number($(this));
    var tr = $(this).parents('tr');
    var quantity_element = tr.find('input.pos_quantity');
    var unit_price_inc_tax = __read_number(tr.find('input.pos_unit_price_inc_tax'));
    var quantity = subtotal / unit_price_inc_tax;
    __write_number(quantity_element, quantity);

    __write_number($(this), subtotal, false);


    if (sell_form_validator) {
        sell_form_validator.element(quantity_element);
    }
    if (pos_form_validator) {
        pos_form_validator.element(quantity_element);
    }
    tr.find('span.pos_line_total_text').text(__currency_trans_from_en(subtotal, true));

    pos_total_row();
});

$('div#product_list_body').on('scroll', function() {


    if ($(this).scrollTop() + $(this).innerHeight() >= $(this)[0].scrollHeight) {
        var page = parseInt($('#suggestion_page').val());
        page += 1;
        $('#suggestion_page').val(page);
        var location_id = $('input#location_id').val();
        var category_id = global_p_category_id;
        var brand_id = global_brand_id;

        var is_enabled_stock = null;
        if ($("#is_enabled_stock").length) {
            is_enabled_stock = $("#is_enabled_stock").val();
        }

        var device_model_id = null;
        if ($("#repair_model_id").length) {
            device_model_id = $("#repair_model_id").val();
        }

        get_product_suggestion_list(category_id, brand_id, location_id, null, is_enabled_stock, device_model_id);
    }
});

$(document).on('ifChecked', '#is_recurring', function() {
    $('#recurringInvoiceModal').modal('show');
});

$(document).on('shown.bs.modal', '#recurringInvoiceModal', function() {
    $('input#recur_interval').focus();
});

$(document).on('click', '#select_all_service_staff', function() {
    var val = $('#res_waiter_id').val();
    $('#pos_table tbody')
        .find('select.order_line_service_staff')
        .each(function() {
            $(this)
                .val(val)
                .change();
        });
});

$(document).on('click', '.print-invoice-link', function(e) {
    e.preventDefault();
    $.ajax({
        url: $(this).attr('href') + "?check_location=true",
        dataType: 'json',
        success: function(result) {
            if (result.success == 1) {
                //Check if enabled or not
                if (result.receipt.is_enabled) {
                    pos_print(result.receipt);
                }
            } else {
                toastr.error(result.msg);
            }

        },
    });
});

function getCustomerRewardPoints() {
    if ($('#reward_point_enabled').length <= 0) {
        return false;
    }
    var is_edit = $('form#edit_sell_form').length || 
    $('form#edit_pos_sell_form').length ? true : false;
    if (is_edit && !customer_set) {
        return false;
    }

    var customer_id = $('#customer_id').val();

    $.ajax({
        method: 'POST',
        url: '/sells/pos/get-reward-details',
        data: { 
            customer_id: customer_id
        },
        dataType: 'json',
        success: function(result) {
            $('#available_rp').text(result.points);
            $('#rp_redeemed_modal').data('max_points', result.points);
            updateRedeemedAmount();
            $('#rp_redeemed_amount').change()
        },
    });
}

function updateRedeemedAmount(argument) {
    var points = $('#rp_redeemed_modal').val().trim();
    points = points == '' ? 0 : parseInt(points);
    var amount_per_unit_point = parseFloat($('#rp_redeemed_modal').data('amount_per_unit_point'));
    var redeemed_amount = points * amount_per_unit_point;
    $('#rp_redeemed_amount_text').text(__currency_trans_from_en(redeemed_amount, true));
    $('#rp_redeemed').val(points);
    $('#rp_redeemed_amount').val(redeemed_amount);
}

//Returns the business default (walk-in) customer id.
//On edit screens #default_customer_id holds the transaction's own contact,
//so #walk_in_customer_contact_id is used when present.
function getWalkInCustomerId() {
    if ($('#walk_in_customer_contact_id').length && $('#walk_in_customer_contact_id').val() != '') {
        return $('#walk_in_customer_contact_id').val();
    }
    return $('#default_customer_id').val();
}

$(document).on('change', 'select#customer_id', function(){
    var default_customer_id = $('#default_customer_id').val();

    //Show walk-in custom name field only for the default walk-in customer
    if ($(this).val() == getWalkInCustomerId()) {
        $('.walk_in_customer_name_wrap').show();
    } else {
        $('.walk_in_customer_name_wrap').hide();
        $('#walk_in_customer_name').val('');
    }

    if ($(this).val() == default_customer_id) {
        //Disable reward points for walkin customers
        if ($('#rp_redeemed_modal').length) {
            $('#rp_redeemed_modal').val('');
            $('#rp_redeemed_modal').change();
            $('#rp_redeemed_modal').attr('disabled', true);
            $('#available_rp').text('');
            updateRedeemedAmount();
            pos_total_row();
        }
    } else {
        if ($('#rp_redeemed_modal').length) {
            $('#rp_redeemed_modal').removeAttr('disabled');
        }
        getCustomerRewardPoints();
    }

    get_sales_orders();
});

$(document).on('change', '#rp_redeemed_modal', function(){
    var points = $(this).val().trim();
    points = points == '' ? 0 : parseInt(points);
    var amount_per_unit_point = parseFloat($(this).data('amount_per_unit_point'));
    var redeemed_amount = points * amount_per_unit_point;
    $('#rp_redeemed_amount_text').text(__currency_trans_from_en(redeemed_amount, true));
    var reward_validation = isValidatRewardPoint();
    if (!reward_validation['is_valid']) {
        toastr.error(reward_validation['msg']);
        $('#rp_redeemed_modal').select();
    }
});

$(document).on('change', '.direct_sell_rp_input', function(){
    updateRedeemedAmount();
    pos_total_row();
});

function isValidatRewardPoint() {
    var element = $('#rp_redeemed_modal');
    var points = element.val().trim();
    points = points == '' ? 0 : parseInt(points);

    var max_points = parseInt(element.data('max_points'));
    var is_valid = true;
    var msg = '';

    if (points == 0) {
        return {
            is_valid: is_valid,
            msg: msg
        }
    }

    var rp_name = $('input#rp_name').val();
    if (points > max_points) {
        is_valid = false;
        msg = __translate('max_rp_reached_error', {max_points: max_points, rp_name: rp_name});
    }

    var min_order_total_required = parseFloat(element.data('min_order_total'));

    var order_total = __read_number($('#final_total_input'));

    if (order_total < min_order_total_required) {
        is_valid = false;
        msg = __translate('min_order_total_error', {min_order: __currency_trans_from_en(min_order_total_required, true), rp_name: rp_name});
    }

    var output = {
        is_valid: is_valid,
        msg: msg,
    }

    return output;
}

function adjustComboQty(tr){
    if(tr.find('input.product_type').val() == 'combo'){
        var qty = __read_number(tr.find('input.pos_quantity'));
        var multiplier = __getUnitMultiplier(tr);

        tr.find('input.combo_product_qty').each(function(){
            $(this).val($(this).data('unit_quantity') * qty * multiplier);
        });
    }
}

$(document).on('change', '#types_of_service_id', function(){
    var types_of_service_id = $(this).val();
    var location_id = $('#location_id').val();

    if(types_of_service_id) {
        $.ajax({
            method: 'POST',
            url: '/sells/pos/get-types-of-service-details',
            data: { 
                types_of_service_id: types_of_service_id,
                location_id: location_id
            },
            dataType: 'json',
            success: function(result) {
                //reset form if price group is changed
                var prev_price_group = $('#types_of_service_price_group').val();
                if(result.price_group_id) {
                    $('#types_of_service_price_group').val(result.price_group_id);
                    $('#price_group_text').removeClass('hide');
                    $('#price_group_text span').text(result.price_group_name);
                } else {
                    $('#types_of_service_price_group').val('');
                    $('#price_group_text').addClass('hide');
                    $('#price_group_text span').text('');
                }
                $('#types_of_service_id').val(types_of_service_id);
                $('.types_of_service_modal').html(result.modal_html);
                
                if (prev_price_group != result.price_group_id) {
                    if ($('form#edit_pos_sell_form').length > 0) {
                        $('table#pos_table tbody').html('');
                        pos_total_row();
                    } else {
                        reset_pos_form();
                    }
                } else {
                    pos_total_row();
                }

                $('.types_of_service_modal').modal('show');
            },
        });
    } else {
        $('.types_of_service_modal').html('');
        $('#types_of_service_price_group').val('');
        $('#price_group_text').addClass('hide');
        $('#price_group_text span').text('');
        $('#packing_charge_text').text('');
        if ($('form#edit_pos_sell_form').length > 0) {
            $('table#pos_table tbody').html('');
            pos_total_row();
        } else {
            reset_pos_form();
        }
    }
});

$(document).on('change', 'input#packing_charge, #additional_expense_value_1, #additional_expense_value_2, \
        #additional_expense_value_3, #additional_expense_value_4', function() {
    pos_total_row();
});

$(document).on('click', '.service_modal_btn', function(e) {
    if ($('#types_of_service_id').val()) {
        $('.types_of_service_modal').modal('show');
    }
});

$(document).on('change', '.payment_types_dropdown', function(e) {
    var default_accounts = $('select#select_location_id').length ? 
                $('select#select_location_id')
                .find(':selected')
                .data('default_payment_accounts') : $('#location_id').data('default_payment_accounts');
    var payment_type = $(this).val();
    var payment_row = $(this).closest('.payment_row');
    if (payment_type && payment_type != 'advance') {
        var default_account = default_accounts && default_accounts[payment_type] && default_accounts[payment_type]['account'] ? 
            default_accounts[payment_type]['account'] : '';
        var row_index = payment_row.find('.payment_row_index').val();

        var account_dropdown = payment_row.find('select#account_' + row_index);
        if (account_dropdown.length && default_accounts) {
            account_dropdown.val(default_account);
            account_dropdown.change();
        }
    }

    //Validate max amount and disable account if advance 
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
            account_dropdown.closest('.form-group').addClass('hide');
        }
    } else {
        amount_element.rules("remove", "max-value");
        if (account_dropdown) {
            account_dropdown.prop('disabled', false); 
            account_dropdown.closest('.form-group').removeClass('hide');
        }    
    }
});

$(document).on('show.bs.modal', '#recent_transactions_modal', function () {
    get_recent_transactions('final', $('div#tab_final'));
});
$(document).on('shown.bs.tab', 'a[href="#tab_quotation"]', function () {
    get_recent_transactions('quotation', $('div#tab_quotation'));
});
$(document).on('shown.bs.tab', 'a[href="#tab_draft"]', function () {
    get_recent_transactions('draft', $('div#tab_draft'));
});

//Pending drafts header indicator: open recent transactions modal on the Draft tab
$(document).on('click', '#pos_draft_indicator', function () {
    $('#recent_transactions_modal').modal('show');
    $('#recent_transactions_modal a[href="#tab_draft"]').tab('show');
});

function disable_pos_form_actions(){
    // Even when offline we still want to lock the buttons so rapid clicks /
    // shortcut spamming do not queue up multiple submits that all fire when
    // the connection comes back.
    if (!window.navigator.onLine) {
        toastr.warning(LANG.you_are_offline || 'You appear to be offline. The request will be retried — please wait.');
    }

    $('div.pos-processing').show();
    $('#pos-save').attr('disabled', 'true');
    $('div.pos-form-actions').find('button').attr('disabled', 'true');
}

function enable_pos_form_actions(){
    $('div.pos-processing').hide();
    $('#pos-save').removeAttr('disabled');
    $('div.pos-form-actions').find('button').removeAttr('disabled');
}

// Build the invoice-preview HTML and show the modal in "confirmation" mode,
// i.e. with a "Confirm & Finalize" button that, once clicked, re-submits the
// POS form with the pos_preview_confirmed flag so submitHandler proceeds.
function show_pos_preview_for_confirmation() {
    // Re-use the existing preview builder so HTML stays in one place.
    $('#pos-preview-invoice').trigger('click');

    // Switch the preview modal into "confirm before finalize" mode.
    var $modal = $('#invoice_preview_modal');
    $modal.find('#pos-preview-confirm-finalize').removeClass('hide');
    // The header still said "preview only - nothing saved"; in this mode the sale is about to be saved.
    var $notice = $modal.find('#invoice_preview_notice');
    if ($notice.length && $notice.data('confirm')) {
        $notice.text($notice.data('confirm')).removeClass('text-muted').addClass('text-warning');
    }
    $modal.find('.modal-title')
        .html('<i class="fa fa-check-circle text-success"></i> ' + (LANG.confirm_and_finalize || 'Confirm & Finalize'));

    // PosInnova "check before recording": the totals, and "Confirm & finalize" waits for the tick
    var $check = $modal.find('#pos_preview_check');
    if (window.PosConfirm && PosConfirm.enabled('sale') && $check.length) {
        var total = __read_number($('#final_total_input')) || 0;
        var paying = 0;
        if ($('#is_credit_sale').val() != '1') {
            $('#payment_rows_div .payment-amount').each(function () { paying += __read_number($(this)) || 0; });
        }
        var diff = Math.round((paying - total) * 100) / 100;
        $('#pos_preview_total').text(__currency_trans_from_en(total, true));
        $('#pos_preview_paying').text(__currency_trans_from_en(paying, true));
        $('#pos_preview_change').text(__currency_trans_from_en(Math.max(diff, 0), true));
        $('#pos_preview_due').text(__currency_trans_from_en(Math.max(-diff, 0), true));
        $('#pos_preview_change_wrap').toggle(diff > 0);
        $('#pos_preview_due_wrap').toggle(diff < 0);
        $('#pos_preview_checkbox').prop('checked', false);
        $check.removeClass('hide');
        $modal.find('#pos-preview-confirm-finalize').prop('disabled', true);
    } else {
        $check.addClass('hide');
        $modal.find('#pos-preview-confirm-finalize').prop('disabled', false);
    }
    // Replace the generic "preview only" alert with a clearer call-to-action.
    var $alert = $modal.find('.invoice-preview .alert');
    if ($alert.length) {
        $alert.removeClass('alert-info').addClass('alert-warning')
            .html('<i class="fa fa-exclamation-triangle"></i> '
                + ($notice.data('alert') || LANG.review_before_finalize_notice || 'Please review the sale below before finalizing the payment.'));
    }
}

// When the modal is closed by any means while in confirmation mode, reset it
// back to plain-preview state so the standalone "Preview Invoice" button works
// normally next time it is clicked.
$(document).on('change', '#pos_preview_checkbox', function() {
    $('#pos-preview-confirm-finalize').prop('disabled', !this.checked);
});

$(document).on('hidden.bs.modal', '#invoice_preview_modal', function() {
    $(this).find('#pos-preview-confirm-finalize').addClass('hide').prop('disabled', false);
    $(this).find('#pos_preview_check').addClass('hide');
    $('#pos_preview_checkbox').prop('checked', false);
    var $notice = $(this).find('#invoice_preview_notice');
    if ($notice.length && $notice.data('preview')) {
        $notice.text($notice.data('preview')).removeClass('text-warning').addClass('text-muted');
    }
});

// User clicked "Confirm & Finalize" inside the preview modal: hide the modal,
// set the one-shot confirmation flag and re-trigger the form submission.
$(document).on('click', '#pos-preview-confirm-finalize', function() {
    // the tick is required when the check is on (the button is disabled; this guards keyboard tricks too)
    if (!$('#pos_preview_check').hasClass('hide') && !$('#pos_preview_checkbox').is(':checked')) {
        return;
    }
    window.pos_preview_confirmed = true;
    $('#invoice_preview_modal').modal('hide');
    // Submit the POS form (validator's submitHandler will see the flag and proceed)
    if (typeof pos_form_obj !== 'undefined' && pos_form_obj && pos_form_obj.length) {
        pos_form_obj.submit();
    }
});

$(document).on('change', '#recur_interval_type', function() {
    if ($(this).val() == 'months') {
        $('.subscription_repeat_on_div').removeClass('hide');
    } else {
        $('.subscription_repeat_on_div').addClass('hide');
    }
});

function validate_discount_field() {
    discount_element = $('#discount_amount_modal');
    discount_type_element = $('#discount_type_modal');

    if ($('#add_sell_form').length || $('#edit_sell_form').length) {
        discount_element = $('#discount_amount');
        discount_type_element = $('#discount_type');
    }
    var max_value = parseFloat(discount_element.data('max-discount'));
    if (discount_element.val() != '' && !isNaN(max_value)) {
        if (discount_type_element.val() == 'fixed') {
            var subtotal = get_subtotal();
            //get max discount amount
            max_value = __calculate_amount('percentage', max_value, subtotal)
        }

        discount_element.rules('add', {
            'max-value': max_value,
            messages: {
                'max-value': discount_element.data('max-discount-error_msg'),
            },
        });
    } else {
        discount_element.rules("remove", "max-value");      
    }
    discount_element.trigger('change');
}

$(document).on('change', '#discount_type_modal, #discount_type', function() {
    validate_discount_field();
});

function update_shipping_address(data) {
    if ($('#shipping_address_div').length) {
        var shipping_address = '';
        if (data.supplier_business_name) {
            shipping_address += data.supplier_business_name;
        }
        if (data.name) {
            shipping_address += ',<br>' + data.name;
        }
        if (data.text) {
            shipping_address += ',<br>' + data.text;
        }
        shipping_address += ',<br>' + data.shipping_address ;
        $('#shipping_address_div').html(shipping_address);
    }
    if ($('#billing_address_div').length) {
        var address = [];
        if (data.supplier_business_name) {
            address.push(data.supplier_business_name);
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
        var billing_address = address.join(', ');
        $('#billing_address_div').html(billing_address);
    }

    if ($('#shipping_custom_field_1').length) {
        let shipping_custom_field_1 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_1 : '';
        $('#shipping_custom_field_1').val(shipping_custom_field_1);
    }

    if ($('#shipping_custom_field_2').length) {
        let shipping_custom_field_2 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_2 : '';
        $('#shipping_custom_field_2').val(shipping_custom_field_2);
    }

    if ($('#shipping_custom_field_3').length) {
        let shipping_custom_field_3 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_3 : '';
        $('#shipping_custom_field_3').val(shipping_custom_field_3);
    }

    if ($('#shipping_custom_field_4').length) {
        let shipping_custom_field_4 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_4 : '';
        $('#shipping_custom_field_4').val(shipping_custom_field_4);
    }

    if ($('#shipping_custom_field_5').length) {
        let shipping_custom_field_5 = data.shipping_custom_field_details != null ? data.shipping_custom_field_details.shipping_custom_field_5 : '';
        $('#shipping_custom_field_5').val(shipping_custom_field_5);
    }
    
    //update export fields
    if (data.is_export) {
        $('#is_export').prop('checked', true);
        $('div.export_div').show();
        if ($('#export_custom_field_1').length) {
            $('#export_custom_field_1').val(data.export_custom_field_1);
        }
        if ($('#export_custom_field_2').length) {
            $('#export_custom_field_2').val(data.export_custom_field_2);
        }
        if ($('#export_custom_field_3').length) {
            $('#export_custom_field_3').val(data.export_custom_field_3);
        }
        if ($('#export_custom_field_4').length) {
            $('#export_custom_field_4').val(data.export_custom_field_4);
        }
        if ($('#export_custom_field_5').length) {
            $('#export_custom_field_5').val(data.export_custom_field_5);
        }
        if ($('#export_custom_field_6').length) {
            $('#export_custom_field_6').val(data.export_custom_field_6);
        }
    } else {
        $('#export_custom_field_1, #export_custom_field_2, #export_custom_field_3, #export_custom_field_4, #export_custom_field_5, #export_custom_field_6').val('');
        $('#is_export').prop('checked', false);
        $('div.export_div').hide();
    }
    
    $('#shipping_address_modal').val(data.shipping_address);
    $('#shipping_address').val(data.shipping_address);
}

function get_sales_orders() {
    if ($('#sales_order_ids').length) {
        if ($('#sales_order_ids').hasClass('not_loaded')) {
            $('#sales_order_ids').removeClass('not_loaded');
            return false;
        }
        var customer_id = $('select#customer_id').val();
        var location_id = $('input#location_id').val();
        $.ajax({
            url: '/get-sales-orders/' + customer_id + '?location_id=' + location_id,
            dataType: 'json',
            success: function(data) {
                $('#sales_order_ids').select2('destroy').empty().select2({data: data});
                $('table#pos_table tbody').find('tr').each( function(){
                    if (typeof($(this).data('so_id')) !== 'undefined') {
                        $(this).remove();
                    }
                });
                pos_total_row();
            },
        });
    }
}

$("#sales_order_ids").on("select2:select", function (e) {
    var sales_order_id = e.params.data.id;
    var product_row = $('input#product_row_count').val();
    var location_id = $('input#location_id').val();
    $.ajax({
        method: 'GET',
        url: '/get-sales-order-lines',
        async: false,
        data: {
            product_row: product_row,
            sales_order_id: sales_order_id,
            is_serial_no: true,

        },
        dataType: 'json',
        success: function(result) {
            if (result.html) {
                var html = result.html;
                $(html).find('tr').each(function(){
                    $('table#pos_table tbody')
                    .append($(this))
                    .find('input.pos_quantity');
                    
                    var this_row = $('table#pos_table tbody')
                        .find('tr')
                        .last();
                    pos_each_row(this_row);

                    product_row = parseInt(product_row) + 1;

                    //For initial discount if present
                    var line_total = __read_number(this_row.find('input.pos_line_total'));
                    this_row.find('span.pos_line_total_text').text(line_total);

                    //Check if multipler is present then multiply it when a new row is added.
                    if(__getUnitMultiplier(this_row) > 1){
                        this_row.find('select.sub_unit').trigger('change');
                    }

                    round_row_to_iraqi_dinnar(this_row);
                    __currency_convert_recursively(this_row);
                });

                set_so_values(result.sales_order);

                //increment row count
                $('input#product_row_count').val(product_row);
                
                pos_total_row();
            
            } else {
                toastr.error(result.msg);
                $('input#search_product')
                    .focus()
                    .select();
            }
        },
    });
});

function set_so_values(so) {
    $('textarea[name="sale_note"]').val(so.additional_notes);
    if ($('#shipping_details').is(':visible')) {
        $('#shipping_details').val(so.shipping_details);
    }
    $('#shipping_address').val(so.shipping_address);
    $('#delivered_to').val(so.delivered_to);
    $('#shipping_charges').val( __number_f(so.shipping_charges));
    $('#shipping_status').val(so.shipping_status);
    if ($('#shipping_custom_field_1').length) {
        $('#shipping_custom_field_1').val(so.shipping_custom_field_1);
    }
    if ($('#shipping_custom_field_2').length) {
        $('#shipping_custom_field_2').val(so.shipping_custom_field_2);
    }
    if ($('#shipping_custom_field_3').length) {
        $('#shipping_custom_field_3').val(so.shipping_custom_field_3);
    }
    if ($('#shipping_custom_field_4').length) {
        $('#shipping_custom_field_4').val(so.shipping_custom_field_4);
    }
    if ($('#shipping_custom_field_5').length) {
        $('#shipping_custom_field_5').val(so.shipping_custom_field_5);
    }
}

$("#sales_order_ids").on("select2:unselect", function (e) {
    var sales_order_id = e.params.data.id;
    $('table#pos_table tbody').find('tr').each( function(){
        if (typeof($(this).data('so_id')) !== 'undefined' 
            && $(this).data('so_id') == sales_order_id) {
            $(this).remove();
        pos_total_row();
        }
    });
});

// =====================================================================
// PosInnova: change base unit of a product directly from the POS row
// (only available for "simple" products with no sub-units configured).
// =====================================================================
(function() {
    var base_units_cache = null;

    function loadBaseUnitsInto($select, current_unit_id, callback) {
        var fillOptions = function(units) {
            $select.empty().append('<option value="">' + (LANG.please_select || 'Please select') + '</option>');
            $.each(units, function(_, u) {
                var label = u.actual_name + ' (' + u.short_name + ')';
                var $opt = $('<option>').val(u.id).text(label);
                if (parseInt(u.id, 10) === parseInt(current_unit_id, 10)) {
                    $opt.prop('disabled', true).text(label + ' — current');
                }
                $select.append($opt);
            });
            if (callback) callback();
        };

        if (base_units_cache !== null) {
            fillOptions(base_units_cache);
            return;
        }

        $.ajax({
            url: '/sells/pos/get-base-units',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res && res.success) {
                    base_units_cache = res.units || [];
                    fillOptions(base_units_cache);
                } else {
                    toastr.error((res && res.msg) || 'Failed to load units.');
                }
            },
            error: function() {
                toastr.error('Failed to load units. Please check your connection.');
            }
        });
    }

    // Build a URL that respects the app's subfolder install (e.g. /posinnova/public).
    function pos_app_url(path) {
        // Try to read base href first; fall back to inferring from window.location
        var base = $('base').attr('href');
        if (base) {
            return base.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
        }
        // Strip the script's known path segment from current pathname
        var pathname = window.location.pathname || '/';
        // Remove trailing /pos/create or similar segments to keep the install root
        var root = pathname.replace(/\/pos\/.*$/, '').replace(/\/sells.*$/, '');
        return root.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
    }

    $(document).on('click', '.pos-change-base-unit-trigger', function(e) {
        e.preventDefault();
        var $trigger = $(this);
        var product_id = $trigger.data('product_id');
        var variation_id = $trigger.data('variation_id');
        var current_unit_id = $trigger.data('current_unit_id');
        var current_unit_name = $trigger.data('current_unit_name') || '';
        var row_index = $trigger.data('row_index');

        $('#cbu_product_id').val(product_id);
        $('#cbu_variation_id').val(variation_id);
        $('#cbu_row_index').val(row_index);
        $('#cbu_current_unit_name').text(current_unit_name);

        var $select = $('#cbu_new_unit_id');
        loadBaseUnitsInto($select, current_unit_id);

        $('#change_base_unit_modal').modal('show');
    });

    $(document).on('click', '#cbu_save_btn', function() {
        var $btn = $(this);
        var product_id = $('#cbu_product_id').val();
        var variation_id = $('#cbu_variation_id').val();
        var row_index = $('#cbu_row_index').val();
        var new_unit_id = $('#cbu_new_unit_id').val();

        if (!new_unit_id) {
            toastr.warning(LANG.please_select || 'Please select a unit.');
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: '/sells/pos/change-product-base-unit',
            method: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                product_id: product_id,
                unit_id: new_unit_id
            },
            success: function(res) {
                if (res && res.success) {
                    toastr.success(res.msg || 'Base unit updated.');
                    $('#change_base_unit_modal').modal('hide');

                    // Refresh the cart row so the new unit + sub-units take effect.
                    var $row = $('table#pos_table tbody tr.product_row').filter(function() {
                        return String($(this).data('row_index')) === String(row_index);
                    }).first();

                    var qty = 1;
                    if ($row.length) {
                        qty = parseFloat($row.find('input.pos_quantity').val()) || 1;
                    }

                    var location_id = $('input[name="location_id"]').val()
                        || $('#select_location_id').val()
                        || $('input#location_id').val();

                    if (!location_id) {
                        // No location? just remove the row, cashier can re-add manually.
                        if ($row.length) { $row.remove(); pos_total_row(); }
                        return;
                    }

                    $.ajax({
                        method: 'GET',
                        url: '/sells/pos/get_product_row/' + variation_id + '/' + location_id,
                        data: {
                            product_row: $('input#product_row_count').val(),
                            quantity: qty
                        },
                        dataType: 'json',
                        success: function(result) {
                            if (result && result.success) {
                                if ($row.length) { $row.remove(); }
                                pos_insert_product_row(result);
                            } else {
                                toastr.warning((result && result.msg) || 'Please re-add the product to refresh the row.');
                                if ($row.length) { $row.remove(); pos_total_row(); }
                            }
                        },
                        error: function() {
                            if ($row.length) { $row.remove(); pos_total_row(); }
                        }
                    });
                } else {
                    toastr.error((res && res.msg) || 'Could not update base unit.');
                }
            },
            error: function(xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.msg)
                    ? xhr.responseJSON.msg
                    : 'Network error. Please try again.';
                toastr.error(msg);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
})();

$(document).on('click', '#add_expense', function(){
    $.ajax({
        url: '/expenses/create',
        data: { 
            location_id: $('#select_location_id').val()
        },
        dataType: 'html',
        success: function(result) {
            $('#expense_modal').html(result);
            $('#expense_modal').modal('show');
        },
    });
});

$(document).on('shown.bs.modal', '#expense_modal', function(){
    $('#expense_transaction_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });
    $('#expense_modal .paid_on').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });
    $(this).find('.select2').select2();
    $('#add_expense_modal_form').validate();
});

$(document).on('hidden.bs.modal', '#expense_modal', function(){
    $(this).html('');
});

$(document).on('submit', 'form#add_expense_modal_form', function(e) {
    e.preventDefault();
    // PosInnova "check before recording": the expense's total, category and location
    var exp_form = this;
    if (window.PosConfirm && PosConfirm.enabled('expense') && exp_form.getAttribute('data-rc-ok') !== '1') {
        var rc = PosConfirm.fromForm(exp_form);
        rc.kind = 'expense';
        rc.amount = __read_number($(exp_form).find('#expense_final_total')) || 0;
        var cat = $.trim($(exp_form).find('select[name="expense_category_id"] option:selected').text());
        if ($(exp_form).find('select[name="expense_category_id"]').val() && cat) { rc.rows.push([PosConfirm.label('category'), cat]); }
        PosConfirm.ask(rc, function () {
            exp_form.setAttribute('data-rc-ok', '1');
            $(exp_form).trigger('submit');
        });
        $(exp_form).find('button[type="submit"]').prop('disabled', false);
        return false;
    }
    exp_form.removeAttribute('data-rc-ok');
    var data = $(this).serialize();

    $.ajax({
        method: 'POST',
        url: $(this).attr('action'),
        dataType: 'json',
        data: data,
        success: function(result) {
            if (result.success == true) {
                $('#expense_modal').modal('hide');
                toastr.success(result.msg);
            } else {
                toastr.error(result.msg);
            }
        },
    });
});

function get_contact_due(id) {
    $.ajax({
        method: 'get',
        url: /get-contact-due/ + id,
        dataType: 'text',
        success: function(result) {
            if (result != '') {
                $('.contact_due_text').find('span').text(result);
                $('.contact_due_text').removeClass('hide');
            } else {
                $('.contact_due_text').find('span').text('');
                $('.contact_due_text').addClass('hide');
            }
        },
    });
}

function submitQuickContactForm(form) {
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
                
                $('select#customer_id').append(
                    $('<option>', { value: result.data.id, text: name })
                );
                $('select#customer_id')
                    .val(result.data.id)
                    .trigger('change');
                $('div.contact_modal').modal('hide');
                update_shipping_address(result.data)
                toastr.success(result.msg);
            } else {
                toastr.error(result.msg);
            }
        },
    });
}

$(document).on('click', '#send_for_sell_return', function(e) {
    var invoice_no = $('#send_for_sell_return_invoice_no').val();

    if (invoice_no) {
        $.ajax({
            method: 'get',
            url: /validate-invoice-to-return/ + encodeURI(invoice_no),
            dataType: 'json',
            success: function(result) {
                if (result.success == true) {
                    window.location = result.redirect_url ;
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    }
})

    $(document).on('click', '#send_for_sercice_staff_replacement', function (e) {
        var invoice_no = $('#send_for_sell_service_staff_invoice_no').val();

        if (invoice_no) {
            $.ajax({
                method: 'get',
                url: /validate-invoice-to-service-staff-replacement/ + encodeURI(invoice_no),
                dataType: 'json',
                success: function (result) {
                    if (result.success == true) {
                        $('#service_staff_replacement').popover('hide');
                        $('#service_staff_modal').html(result.msg);
                        $('#service_staff_modal').modal('show');
                       
                    } else {
                        toastr.error(result.msg);
                    }
                },
            });
        }
    });

    $(document).on('shown.bs.modal', '#service_staff_modal', function () {
        $('#change_service_staff').validate();
    });


    $(document).on('submit', 'form#change_service_staff', function (e) {
        e.preventDefault();
        var data = $(this).serialize();

        $.ajax({
            method: 'POST',
            url: $(this).attr('action'),
            dataType: 'json',
            data: data,
            success: function (result) {
                if (result.success == true) {
                    $('#service_staff_modal').modal('hide');
                    toastr.success(result.msg);
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

$(document).on('ifChanged', 'input[name="search_fields[]"]', function(event) {
    var search_fields = [];
    $('input[name="search_fields[]"]:checked').each(function() {
       search_fields.push($(this).val());
    });

    localStorage.setItem('pos_search_fields', search_fields);
});

function set_search_fields() {
    if ($('input[name="search_fields[]"]').length == 0) {
        return false;
    }

    var pos_search_fields = localStorage.getItem('pos_search_fields');

    if (pos_search_fields === null) {
        pos_search_fields = ['name', 'sku', 'lot'];
    }

    $('input[name="search_fields[]"]').each(function() {
        if (pos_search_fields.indexOf($(this).val()) >= 0) {
            $(this).iCheck('check');
        } else {
            $(this).iCheck('uncheck');
        }
    });
}

$(document).on('click', '#show_service_staff_availability', function(){
    loadServiceStaffAvailability();
})
$(document).on('click', '#refresh_service_staff_availability_status', function(){
    loadServiceStaffAvailability(false);
})
$(document).on('click', 'button.pause_resume_timer', function(e){
    $('.view_modal').find('.overlay').removeClass('hide');
    $.ajax({
        method: 'get',
        url: $(this).attr('data-href'),
        dataType: 'json',
        success: function(result) {
            loadServiceStaffAvailability(false);
        },
    });
})

$(document).on('click', '.mark_as_available', function(e){
    e.preventDefault()
    $('.view_modal').find('.overlay').removeClass('hide');
    $.ajax({
        method: 'get',
        url: $(this).attr('href'),
        dataType: 'json',
        success: function(result) {
            loadServiceStaffAvailability(false);
        },
    });
})
var service_staff_availability_interval = null;

function loadServiceStaffAvailability(show = true) {
    var location_id = $('[name="location_id"]').val();
    $.ajax({
        method: 'get',
        url: $('#show_service_staff_availability').attr('data-href'),
        dataType: 'html',
        data: {location_id: location_id},
        success: function(result) {
            $('.view_modal').html(result);
            if (show) {
                $('.view_modal').modal('show')

                //auto refresh service staff availabilty if modal is open
                service_staff_availability_interval = setInterval(function () {
                    loadServiceStaffAvailability(false);
                }, 60000);
            }
        },
    });
}

$(document).on('hidden.bs.modal', '.view_modal', function(){
    if (service_staff_availability_interval !== null) {
        clearInterval(service_staff_availability_interval);
    }
    service_staff_availability_interval = null;
});


$(document).on('change', '#res_waiter_id', function(e){
    var is_enable = $(this).find('option:selected').data('is_enable');

    if(is_enable){
        swal({
            text: LANG.enter_pin_here,
            buttons: true,
            dangerMode: true,
            content: {
                element: "input",
                attributes: {
                    placeholder: LANG.enter_pin_here,
                    type: "password",
                },
            },
        })
        .then((inputValue) => {
            if (inputValue !== null) {
                    $.ajax({
                        method: 'get',
                        url: '/modules/data/check-staff-pin',
                        dataType: 'json',
                        data: {
                        service_staff_pin: inputValue,
                        user_id : $("#res_waiter_id").val(),
                        },
                        success: (result) => {

                            if (result == false) {
                                toastr.error(LANG.authentication_failed);
                                $("#res_waiter_id").val('');
                            } else {
                                // AJAX request succeeded, resolve
                                toastr.success(LANG.authentication_successfull);
                            }
                        },
                    });
            } else {
                // Handle the "Cancel" action
                $("#res_waiter_id").val('');
            }
        });
        
    }
})

// update serial number of product item
function update_serial_no(){
    $('.product_row').each(function (index) {
        // Add the serial number to the first <td> of each row (index + 1 to start from 1)
        if ($(this).find('td:first').hasClass('serial_no')) {
            $(this).find('td:first').text(index + 1);
        }
    });
}


//PosInnova: the customer display (/customer-display) shows a thank-you screen
//with what was paid and the change. It is written just before the form is
//cleared, because after that the totals are gone.
function pos_display_announce_sale(result) {
    try {
        var methods = [];
        $('#payment_rows_div select.payment_types_dropdown').each(function() {
            var idx = String(this.id || '').replace('method_', '');
            var amount = $('#amount_' + idx).length ? __read_number($('#amount_' + idx)) : 0;
            if ($(this).val() && amount > 0) {
                methods.push($(this).val());
            }
        });
        localStorage.setItem('pos_display_last_sale', JSON.stringify({
            at: Date.now(),
            total: __read_number($('input#final_total_input')),
            paid: __read_number($('input#total_paying_input')),
            change: __read_number($('input#change_return')),
            balance: __read_number($('input#in_balance_due')),
            methods: methods,
            location_id: $('input#location_id').val(),
            invoice_no: (result && result.invoice_no) ? result.invoice_no : ''
        }));
    } catch (e) {}
}

$(document).on('shown.bs.modal hidden.bs.modal', '#modal_payment', function() {
    if (typeof saveFormDataToLocalStorage === 'function') {
        saveFormDataToLocalStorage();
    }
});

/**
 * Saves the serialized form data from #add_pos_sell_form into LocalStorage.
 */
function saveFormDataToLocalStorage() {


    // Check if global_is_clear_local_storage is true and reset it to false if so
    if(global_is_clear_local_storage){
        localStorage.setItem("pos_form_data_array", JSON.stringify([]));
        global_is_clear_local_storage = false;
        //PosInnova: the sale is done (or the form was cleared), so there is
        //nothing left to offer back on the next page load.
        pos_clear_cart_snapshot();
        return false; // Exit the function early if global_is_clear_local_storage was true
    }

    // var storedArrayData = JSON.parse(localStorage.getItem("pos_form_data_array"));

    // console.log("All data afer clear:", storedArrayData);

    // Auto-save only applies on the POS *create* screen. On the edit screen
    // (form id #edit_pos_sell_form) or any other view that lacks the add form,
    // silently no-op instead of spamming the console with errors.
    let form = $('form#add_pos_sell_form');
    if (form.length === 0) {
        return;
    }
    // Serialize form data into an array of objects: [{name: 'input_name', value: 'input_value'}, ...]
    let formArray = form.serializeArray();

    // Find if "price_total" already exists in the array
    let priceIndex = formArray.findIndex(item => item.name === "price_total");

    if (priceIndex !== -1) {
        // If exists, update the value
        formArray[priceIndex].value = get_subtotal();
    } else {
        // If not exists, push new entry
        formArray.push({ name: "price_total", value: get_subtotal() });
    }

    // Find if "order_tax" already exists in the array
    let textIndex = formArray.findIndex(item => item.name === "order_tax");

    if (textIndex !== -1) {
        // If exists, update the value
        formArray[textIndex].value = $("#order_tax").text().trim();
    } else {
        // If not exists, push new entry
        formArray.push({ name: "order_tax", value: $("#order_tax").text().trim()});
    }

    // Find if "shipping_charges_amount" already exists in the array
    let shipping_charges_amount = formArray.findIndex(item => item.name === "shipping_charges_amount");

    if (shipping_charges_amount !== -1) {
        // If exists, update the value
        formArray[shipping_charges_amount].value = $("#shipping_charges_amount").text().trim();
    } else {
        // If not exists, push new entry
        formArray.push({ name: "shipping_charges_amount", value: $("#shipping_charges_amount").text().trim()});
    }

    // Find if "total_paying_input" already exists in the array
    let total_paying_input = formArray.findIndex(item => item.name === "total_paying_input");
    
    if (total_paying_input !== -1) {
        // If exists, update the value
        formArray[total_paying_input].value = $("#total_paying_input").val();
    } else {
        // If not exists, push new entry
        formArray.push({ name: "total_paying_input", value: $("#total_paying_input").val()});
    }

    // Find if "change_return" already exists in the array
    let change_return = formArray.findIndex(item => item.name === "change_return");
    if (change_return !== -1) {
        // If exists, update the value
        formArray[change_return].value = $("#change_return").val();
    } else {
        // If not exists, push new entry
        formArray.push({ name: "change_return", value: $("#change_return").val()});
    }
     // Find if "in_balance_due" already exists in the array
     let in_balance_due = formArray.findIndex(item => item.name === "in_balance_due");
     if (in_balance_due !== -1) {
         // If exists, update the value
         formArray[in_balance_due].value = $("#in_balance_due").val();
     } else {
         // If not exists, push new entry
         formArray.push({ name: "in_balance_due", value: $("#in_balance_due").val()});
     }
    //PosInnova: tell the customer display whether the payment window is open,
    //so it can switch from the item list to the payment and change.
    formArray.push({ name: "__display_paying", value: $('#modal_payment').is(':visible') ? 1 : 0 });

    // Store serialized data in LocalStorage as a JSON string
    localStorage.setItem("pos_form_data_array", JSON.stringify(formArray));

    //PosInnova: keep the cart-recovery snapshot in step. Hooked here rather
    //than at each call site so every path that already saves form state - adding
    //a row, changing a quantity, changing the customer, entering a payment -
    //keeps the recoverable cart current too.
    pos_save_cart_snapshot();

    // console.log("Form data successfully saved to LocalStorage.");
}

// Open Receive Payment (pay contact due) modal from POS
$(document).on('click', '.pos_pay_contact_due', function (e) {
    e.preventDefault();
    var customer_id = $('select#customer_id').val();
    var default_customer_id = $('#default_customer_id').val();
    if (!customer_id) {
        toastr.warning('Please select a customer first');
        return;
    }
    if (default_customer_id && customer_id == default_customer_id) {
        toastr.warning('Cannot receive payment from walk-in customer. Select a real customer first.');
        return;
    }
    // Build URL from the template URL placed in data-href (ends with /0)
    var template = $(this).data('href');
    var url = template.replace(/\/0$/, '/' + customer_id) + '?type=sell';
    $.ajax({
        url: url,
        dataType: 'html',
        success: function (result) {
            $('.pay_contact_due_modal').html(result).modal('show');
            __currency_convert_recursively($('.pay_contact_due_modal'));
            $('#paid_on').datetimepicker({
                format: moment_date_format + ' ' + moment_time_format,
                ignoreReadonly: true,
            });
            $('.pay_contact_due_modal').find('form#pay_contact_due_form').validate();
        },
    });
});

/* ==========================================================================
 * PosInnova: cart recovery
 *
 * A part-built sale lives only in the DOM. An accidental refresh, a closed
 * tab or a browser crash loses it, and the cashier has to remember what was
 * already scanned. This keeps a snapshot of the cart in this browser and
 * offers it back on the next visit to the POS screen.
 *
 * Three rules make it safe to use in a pharmacy:
 *
 *   1. It never restores by itself. The cashier is shown what was found and
 *      has to press a button. A stale cart can therefore never turn into a
 *      sale nobody meant to make.
 *   2. Restoring re-fetches every line from the server, exactly the way
 *      scanning the product again would. Price, stock, tax and batch come
 *      from the database as it is now - never from the snapshot. A line whose
 *      product is no longer sellable at this location is dropped and named.
 *   3. If a price moved since the snapshot was taken, the cashier is told
 *      which products, so nothing changes quietly under them.
 *
 * Quantity and customer are the only things taken from the snapshot, and both
 * are re-validated by the server when the sale is finalized.
 *
 * Deliberately NOT restored: payment lines, discounts, order tax and shipping.
 * Those are decided at the end of a sale against a total that no longer
 * exists once the lines are re-priced, so replaying them could misstate what
 * the customer owes.
 * ========================================================================== */

var POS_CART_SNAPSHOT_KEY = 'posinnova_cart_snapshot_v1';
var pos_cart_restore_in_progress = false;

//PosInnova: false until pos_offer_cart_recovery() has had its chance to read
//the snapshot. Until then an empty cart must NOT delete it. The POS calls
//pos_total_row() during page init - set_default_customer() triggers the
//select#customer_id change handler, whose reward-point branch calls it - and
//the cart is legitimately empty at that moment, so the snapshot was being
//destroyed before it could ever be offered back.
var pos_cart_recovery_settled = false;

/**
 * Read the config and translated strings the blade handed us.
 * Returns null on any screen that has no recovery element (POS edit, etc).
 */
function pos_cart_recovery_cfg() {
    var $el = $('#pos_cart_recovery');
    if (!$el.length) {
        return null;
    }
    //attr() not data(): data() keeps its own cache and normalises key names,
    //and we only ever want the literal value the server rendered.
    var a = function (name) {
        var v = $el.attr('data-' + name);
        return typeof v === 'undefined' ? '' : String(v);
    };

    return {
        $el: $el,
        user_id: a('user-id'),
        business_id: a('business-id'),
        max_age_mins: parseInt(a('max-age-mins'), 10) || 720,
        lang: {
            heading: a('lang-heading'),
            help: a('lang-help'),
            restore: a('lang-restore'),
            discard: a('lang-discard'),
            restoring: a('lang-restoring'),
            restored: a('lang-restored'),
            discarded: a('lang-discarded'),
            price_changed: a('lang-price-changed'),
            skipped: a('lang-skipped')
        }
    };
}

/**
 * Read the current cart out of the DOM.
 *
 * Fields are matched on their exact name (products[IDX][field]) rather than a
 * suffix, so a combo product child row - products[IDX][combo][K][field] -
 * cannot be mistaken for a line of its own.
 */
function pos_collect_cart_lines() {
    var lines = [];

    $('table#pos_table tbody tr.product_row').each(function () {
        var $tr = $(this);
        var idx = $tr.attr('data-row_index');
        if (typeof idx === 'undefined' || idx === '') {
            return;
        }

        var field = function (name) {
            return $tr.find('[name="products[' + idx + '][' + name + ']"]');
        };

        var variation_id = field('variation_id').val();
        if (!variation_id) {
            return;
        }

        var $name_cell = $tr.children('td').not('.serial_no').first();
        var name = $.trim(($name_cell.text() || '').replace(/\s+/g, ' ')).substring(0, 60);

        lines.push({
            variation_id: String(variation_id),
            product_id: String(field('product_id').val() || ''),
            quantity: String(field('quantity').val() || ''),
            unit_price: String(field('unit_price').val() || ''),
            lot_no_line_id: String(field('lot_no_line_id').val() || ''),
            sub_unit_id: String(field('sub_unit_id').val() || ''),
            line_location_id: String(field('location_id').val() || ''),
            //Everything below is cashier-editable on the row. Without it a
            //restored line came back at the catalogue price with no discount,
            //no row tax override and no note - not the cart they had.
            line_discount_amount: String(field('line_discount_amount').val() || ''),
            line_discount_type: String(field('line_discount_type').val() || ''),
            tax_id: String(field('tax_id').val() || ''),
            sell_line_note: String(field('sell_line_note').val() || ''),
            name: name
        });
    });

    return lines;
}

/**
 * Overwrite the snapshot with the cart as it stands. An empty cart removes it,
 * so finishing or clearing a sale leaves nothing to offer back.
 *
 * Every localStorage call is guarded: it throws in private-browsing mode and
 * when the origin quota is full, and losing the snapshot must never break the
 * sale in progress.
 */
function pos_save_cart_snapshot() {
    if (pos_cart_restore_in_progress) {
        return;
    }

    var cfg = pos_cart_recovery_cfg();
    if (!cfg || $('form#add_pos_sell_form').length === 0) {
        return;
    }

    try {
        var lines = pos_collect_cart_lines();
        if (!lines.length) {
            //An empty cart means "nothing worth keeping" only once the page has
            //settled. While it is still initialising it just means the rows are
            //not on screen yet, and the snapshot is what we are about to offer.
            if (pos_cart_recovery_settled) {
                pos_clear_cart_snapshot();
            }

            return;
        }

        localStorage.setItem(POS_CART_SNAPSHOT_KEY, JSON.stringify({
            v: 2,
            ts: Date.now(),
            user_id: cfg.user_id,
            business_id: cfg.business_id,
            location_id: String($('input#location_id').val() || ''),
            contact_id: String($('select#customer_id').val() || ''),
            contact_text: $.trim($('select#customer_id option:selected').text() || ''),
            lines: lines
        }));
    } catch (e) {
        /* best effort only */
    }
}

function pos_clear_cart_snapshot() {
    try {
        localStorage.removeItem(POS_CART_SNAPSHOT_KEY);
    } catch (e) {
        /* best effort only */
    }
}

/**
 * Return the snapshot only if it is safe to offer on this screen.
 *
 * A snapshot belonging to another cashier or another business is left in place
 * rather than deleted - that cashier gets it back when they log in again - but
 * it is never shown here.
 */
function pos_read_cart_snapshot() {
    var cfg = pos_cart_recovery_cfg();
    if (!cfg) {
        return null;
    }

    var raw = null;
    try {
        raw = localStorage.getItem(POS_CART_SNAPSHOT_KEY);
    } catch (e) {
        return null;
    }
    if (!raw) {
        return null;
    }

    var snap = null;
    try {
        snap = JSON.parse(raw);
    } catch (e) {
        pos_clear_cart_snapshot();

        return null;
    }

    if (!snap || snap.v !== 2 || !snap.lines || !snap.lines.length) {
        pos_clear_cart_snapshot();

        return null;
    }

    //Another user or another business on the same PC: not ours to show.
    if (String(snap.user_id) !== cfg.user_id || String(snap.business_id) !== cfg.business_id) {
        return null;
    }

    //Too old to be a refresh. Also catches a clock that moved backwards.
    var age_mins = (Date.now() - (parseInt(snap.ts, 10) || 0)) / 60000;
    if (!(age_mins >= 0) || age_mins > cfg.max_age_mins) {
        pos_clear_cart_snapshot();

        return null;
    }

    //A different location means different stock and different prices. Keep it
    //for when they switch back, but do not offer it against this one.
    if (String(snap.location_id || '') !== String($('input#location_id').val() || '')) {
        return null;
    }

    return snap;
}

/**
 * Show the recovery banner, if there is anything to recover.
 */
function pos_offer_cart_recovery() {
    var cfg = pos_cart_recovery_cfg();
    if (!cfg || $('form#add_pos_sell_form').length === 0) {
        pos_cart_recovery_settled = true;

        return;
    }

    //Already showing one, or a cart is already on screen (a draft, quotation
    //or sales order was loaded into the page): not a recovery case.
    if ($('#pos_cart_recovery_banner').length) {
        return;
    }
    //PosInnova: dispensing a prescription or a ward order fills the cart itself; an old cart
    //offered on top of it would put the same medicines in twice.
    if ($('#prescription_consultation_id').val() || $('#ward_dispense_admission_id').val()) {
        pos_cart_recovery_settled = true;

        return;
    }
    if ($('table#pos_table tbody tr.product_row').length > 0) {
        pos_cart_recovery_settled = true;

        return;
    }

    var snap = pos_read_cart_snapshot();
    if (!snap) {
        pos_cart_recovery_settled = true;

        return;
    }

    //A banner is going up. Deliberately leave the snapshot unsettled so that a
    //stray pos_total_row() on the still-empty cart cannot delete the very
    //thing the cashier is being offered.

    var when = '';
    try {
        when = (typeof moment !== 'undefined')
            ? moment(snap.ts).format('LT')
            : new Date(snap.ts).toLocaleTimeString();
    } catch (e) {
        when = '';
    }

    var help = cfg.lang.help
        .replace('__COUNT__', snap.lines.length)
        .replace('__TIME__', when);

    //type=button on both: a stray Enter or click must never submit the sale.
    var $banner = $(
        '<div class="alert alert-warning" id="pos_cart_recovery_banner" style="margin-bottom: 8px;">' +
            '<i class="fa fa-history"></i> <strong class="pos_cart_recovery_heading"></strong> ' +
            '<span class="pos_cart_recovery_help"></span>' +
            '<div style="margin-top: 6px;">' +
                '<button type="button" class="btn btn-primary btn-sm" id="pos_cart_recovery_restore"></button> ' +
                '<button type="button" class="btn btn-default btn-sm" id="pos_cart_recovery_discard"></button> ' +
                '<span class="pos_cart_recovery_status text-muted" style="margin-left: 8px;"></span>' +
            '</div>' +
        '</div>'
    );

    //text(), never html(): product names and the translated strings go in as
    //text so a name containing markup cannot be rendered.
    $banner.find('.pos_cart_recovery_heading').text(cfg.lang.heading);
    $banner.find('.pos_cart_recovery_help').text(help);
    $banner.find('#pos_cart_recovery_restore').text(cfg.lang.restore);
    $banner.find('#pos_cart_recovery_discard').text(cfg.lang.discard);

    $banner.data('pos_snapshot', snap);
    $('form#add_pos_sell_form').prepend($banner);
}

/**
 * Put one snapshot line's cashier-edited state back onto a freshly inserted
 * row: the sub-unit, the row tax override, the line discount, the price they
 * typed and the line note.
 *
 * Returns true when the catalogue price has moved since the snapshot was
 * taken. The row keeps the cashier's price either way - this is what lets the
 * caller warn about a line it has deliberately NOT repriced.
 */
function pos_apply_line_state($row, line) {
    //Re-select the stored unit FIRST. The sub_unit handler rescales
    //the price by the unit multiplier, and the snapshot price was
    //recorded with this unit already applied, so the unit has to be
    //back in place before the price is written.
    if (line.sub_unit_id) {
        var $sub = $row.find('select.sub_unit');
        if ($sub.length && $sub.find('option[value="' + line.sub_unit_id + '"]').length) {
            $sub.val(line.sub_unit_id).trigger('change');
        }
    }

    //What the catalogue charges right now, read before anything is
    //overwritten, so the cashier can be told if it has moved.
    var live_price = __read_number($row.find('input.pos_unit_price'));
    var snap_price = __num_uf(line.unit_price);

    //Row tax override. Set the VALUE ONLY: the select.tax_id change
    //handler back-computes unit_price from the tax-inclusive figure,
    //which would discard the price we are about to restore.
    if (line.tax_id) {
        var $tax = $row.find('select.tax_id');
        if ($tax.length && $tax.find('option[value="' + line.tax_id + '"]').length) {
            $tax.val(line.tax_id);
        }
    }

    //Line discount, values only for the same reason.
    if (line.line_discount_type) {
        var $dtype = $row.find('select.row_discount_type');
        if ($dtype.length && $dtype.find('option[value="' + line.line_discount_type + '"]').length) {
            $dtype.val(line.line_discount_type);
        }
    }
    var snap_discount = __num_uf(line.line_discount_amount);
    if (!isNaN(snap_discount)) {
        __write_number($row.find('input.row_discount_amount'), snap_discount);
    }

    //The cashier's own price, then ONE change event. The
    //input.pos_unit_price handler re-reads the discount and the tax
    //rate off this row and rewrites the tax-inclusive price, item_tax,
    //the line total and the cart totals, so the whole row lands
    //consistent instead of us computing derived figures by hand.
    if (!isNaN(snap_price)) {
        __write_number($row.find('input.pos_unit_price'), snap_price);
    }
    $row.find('input.pos_unit_price').change();

    if (line.sell_line_note) {
        $row.find('textarea[name$="[sell_line_note]"]').val(line.sell_line_note);
    }

    //The cart is back the way they left it. Report a catalogue price that has
    //moved underneath it, because this row is deliberately not at that price.
    return (!isNaN(snap_price) && !isNaN(live_price) && Math.abs(snap_price - live_price) > 0.0001);
}

/**
 * Put the snapshot lines back, one at a time, through the same server endpoint
 * that scanning a product uses.
 *
 * Sequential on purpose: it preserves the order the cashier originally scanned
 * in, and it keeps the row-index reservation simple to follow.
 */
function pos_restore_cart_snapshot(snap) {
    var cfg = pos_cart_recovery_cfg();
    if (!cfg || !snap || !snap.lines || !snap.lines.length) {
        return;
    }

    var $banner = $('#pos_cart_recovery_banner');
    $banner.find('button').prop('disabled', true);
    $banner.find('.pos_cart_recovery_status').text(cfg.lang.restoring);

    //Restore the customer FIRST. The price a line is fetched at depends on the
    //customer price group, so the rows must be requested with the customer
    //already in place.
    if (snap.contact_id && String($('select#customer_id').val() || '') !== String(snap.contact_id)) {
        var $cust = $('select#customer_id');
        if ($cust.find('option[value="' + snap.contact_id + '"]').length === 0 && snap.contact_text) {
            $cust.append($('<option>', { value: snap.contact_id, text: snap.contact_text }));
        }
        $cust.val(snap.contact_id).trigger('change');
    }

    var header_location_id = $('input#location_id').val();
    var price_group = $('#price_group').length ? $('#price_group').val() : '';
    var is_direct_sell = ($('input[name="is_direct_sale"]').length > 0 && $('input[name="is_direct_sale"]').val() == 1);

    var restored = 0;
    var skipped = [];
    var price_changed = [];

    //Suppress snapshot writes while rows are going in, so a half-restored cart
    //never overwrites the snapshot we are still reading from.
    pos_cart_restore_in_progress = true;

    var finish = function () {
        pos_cart_restore_in_progress = false;
        pos_cart_recovery_settled = true;

        if (typeof pos_reindex_product_rows === 'function') {
            pos_reindex_product_rows();
        }
        pos_total_row();

        //The snapshot has served its purpose. pos_total_row() has just written
        //a fresh one from the cart that is now on screen.
        pos_clear_cart_snapshot();
        pos_save_cart_snapshot();
        $('#pos_cart_recovery_banner').remove();

        if (restored > 0) {
            toastr.success(cfg.lang.restored.replace('__COUNT__', restored));
        }
        if (price_changed.length) {
            toastr.warning(cfg.lang.price_changed.replace('__PRODUCTS__', price_changed.join(', ')));
        }
        if (skipped.length) {
            toastr.warning(cfg.lang.skipped.replace('__PRODUCTS__', skipped.join(', ')));
        }

        if (!$('#__is_mobile').length) {
            $('input#search_product').focus();
        }
    };

    var add_line = function (i) {
        if (i >= snap.lines.length) {
            finish();

            return;
        }

        var line = snap.lines[i];
        var row_location_id = line.line_location_id || header_location_id;

        $.ajax({
            method: 'GET',
            url: '/sells/pos/get_product_row/' + line.variation_id + '/' + row_location_id,
            data: {
                product_row: $('input#product_row_count').val(),
                customer_id: $('select#customer_id').val(),
                is_direct_sell: is_direct_sell,
                price_group: price_group,
                //Ask for the same batch. If it is gone the server falls back to
                //its normal batch choice rather than failing.
                purchase_line_id: line.lot_no_line_id,
                quantity: line.quantity,
                line_location_id: row_location_id
            },
            dataType: 'json'
        }).done(function (result) {
            if (!result || !result.success || !result.html_content) {
                //Out of stock, discontinued, or no longer sold here.
                skipped.push(line.name || line.variation_id);

                return;
            }

            pos_insert_product_row(result);
            restored++;

            var $row = $('table#pos_table tbody').find('tr').last();

            if (pos_apply_line_state($row, line)) {
                price_changed.push(line.name || line.variation_id);
            }        }).fail(function () {
            skipped.push(line.name || line.variation_id);
        }).always(function () {
            add_line(i + 1);
        });
    };

    add_line(0);
}

$(document).ready(function () {
    $(document).on('click', '#pos_cart_recovery_restore', function (e) {
        e.preventDefault();
        var snap = $('#pos_cart_recovery_banner').data('pos_snapshot');
        if (snap) {
            pos_restore_cart_snapshot(snap);
        }
    });

    $(document).on('click', '#pos_cart_recovery_discard', function (e) {
        e.preventDefault();
        var cfg = pos_cart_recovery_cfg();
        pos_cart_recovery_settled = true;
        pos_clear_cart_snapshot();
        $('#pos_cart_recovery_banner').remove();
        if (cfg) {
            toastr.info(cfg.lang.discarded);
        }
    });

    //Let select2, the product search and set_default_customer() settle first,
    //so the banner is not built against a half-initialised form.
    setTimeout(pos_offer_cart_recovery, 500);
});

/* ==========================================================================
 * PosInnova: the category and brand pickers expand in place.
 *
 * They used to be DaisyUI drawers that slid a half-screen overlay over the
 * cart. The markup in pos_sidebar.blade.php is now a panel under each button,
 * but every class the older handlers bind to is still there, so none of the
 * filtering logic above had to change:
 *
 *   .main-category[data-parent=0]  applies that category and closes the picker
 *   .main-category[data-parent=1]  drills into .all-sub-category
 *   .product_category              applies a sub-category
 *   .product_brand                 applies a brand
 *   .overlay-category/.overlay-brand  "close the picker" - handled here by
 *                                  collapsing the panel, which is what the
 *                                  existing $('.overlay-category').trigger('click')
 *                                  calls now end up doing.
 * ========================================================================== */
$(document).ready(function () {
    var $bar = $('#pi_facet_bar');
    if ($bar.length === 0) {
        return;
    }

    //Native trim, not $.trim: jQuery 4 removed $.trim and this module should
    //not be the thing that breaks on that upgrade.
    function piTrim(v) {
        return String(v == null ? '' : v).replace(/^\s+|\s+$/g, '');
    }

    var PANELS = {
        cat: { $btn: $('#pi_cat_btn'), $panel: $('#pi_cat_panel') },
        brand: { $btn: $('#pi_brand_btn'), $panel: $('#pi_brand_panel') }
    };

    function setPanel(which) {
        $.each(PANELS, function (key, p) {
            if (p.$panel.length === 0) {
                return;
            }
            var on = key === which;
            p.$panel.toggleClass('pi-open', on);
            p.$btn.attr('aria-expanded', on ? 'true' : 'false');
        });
    }

    function currentPanel() {
        var open = '';
        $.each(PANELS, function (key, p) {
            if (p.$panel.hasClass('pi-open')) {
                open = key;
            }
        });

        return open;
    }

    $.each(PANELS, function (key, p) {
        if (p.$btn.length === 0) {
            return;
        }
        p.$btn.on('click', function (e) {
            e.preventDefault();
            setPanel(currentPanel() === key ? '' : key);
        });
    });

    //What used to close the drawer now closes the panel. Bound on both the
    //element and the document so a trigger() from the older handlers is caught
    //whichever way it is dispatched.
    $(document).on('click', '.overlay-category, .overlay-brand', function () {
        setPanel('');
    });

    //Type-to-filter inside a panel. Hides options, never removes them, so the
    //handlers bound to those same elements at page load keep working.
    $bar.on('input', '.pi-facet-filter', function () {
        var term = piTrim($(this).val()).toLowerCase();
        var $scope = $($(this).data('scope'));
        $scope.find('.pi-opt').each(function () {
            var $o = $(this);
            //Never hide the "all" escape hatch - it is how a filter is cleared.
            if ($o.attr('data-value') === 'all') {
                return;
            }
            var hit = term === '' || piTrim($o.text()).toLowerCase().indexOf(term) !== -1;
            $o.prop('hidden', !hit);
        });
    });

    /* ---- the chips that say what is filtering the grid -------------------- */
    var active = { cat: null, brand: null };

    function labelOf($el) {
        var t = piTrim($el.text());

        return t.replace(/\s+/g, ' ');
    }

    function clearFilter(kind) {
        //Re-use the real "all" option so the request goes out exactly as it
        //would if the cashier had clicked it.
        var sel = kind === 'cat'
            ? '.main-category[data-value="all"]'
            : '.product_brand[data-value="all"]';
        var $all = $(sel).first();
        if ($all.length) {
            $all.trigger('click');
        } else {
            active[kind] = null;
            renderChips();
        }
    }

    function renderChips() {
        var $row = $('#pi_active_row');
        $row.empty();

        var add = function (kind, text) {
            var $chip = $('<span class="pi-chip"></span>');
            $('<span></span>').text(text).appendTo($chip);
            var $x = $('<button type="button" aria-label="Clear filter">&times;</button>');
            $x.on('click', function (e) {
                e.preventDefault();
                clearFilter(kind);
            });
            $chip.append($x);
            $row.append($chip);
        };

        if (active.cat) { add('cat', active.cat); }
        if (active.brand) { add('brand', active.brand); }
    }

    function markActive($panel, $opt) {
        $panel.find('.pi-opt').removeClass('pi-opt-active');
        if ($opt) {
            $opt.addClass('pi-opt-active');
        }
    }

    //Bound after the filtering handlers above, so by the time these run the
    //request has already gone out with the new category or brand.
    $('.main-category, .product_category').on('click', function () {
        var $o = $(this);
        if ($o.attr('data-parent') === '1') {
            return;   //drilling down, not choosing
        }
        active.cat = $o.attr('data-value') === 'all' ? null : labelOf($o);
        markActive($('#pi_cat_panel'), active.cat ? $o : null);
        renderChips();
    });

    $('.product_brand').on('click', function () {
        var $o = $(this);
        active.brand = $o.attr('data-value') === 'all' ? null : labelOf($o);
        markActive($('#pi_brand_panel'), active.brand ? $o : null);
        renderChips();
    });

    renderChips();
});
