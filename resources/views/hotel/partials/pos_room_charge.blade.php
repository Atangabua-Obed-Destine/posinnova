{{--
    PosInnova hotel: "Charge to room" on the POS screen. Pick a guest who is in the hotel now; the sale
    is saved as a credit sale on that guest and goes on the stay's bill (collected at check-out).
    Included by sale_pos/create when the hotel allows it. Also used by E-Ride room service (pos_panel).
--}}
@php
    $rc_T = collect(['btn', 'title', 'search', 'none', 'pick', 'empty_cart', 'confirm', 'until', 'no_credit', 'undo'])
        ->mapWithKeys(fn ($k) => [$k => __('hotel.rcp_'.$k)])->all();
@endphp
<style>
#ht_rc_btn { position: fixed; left: 16px; bottom: 92px; z-index: 1035; display: inline-flex; align-items: center; gap: 8px; border: 0; border-radius: 999px;
    padding: 10px 16px; background: #1849a9; color: #fff; font-weight: 700; box-shadow: 0 6px 16px rgba(16,24,40,.25); cursor: pointer; }
#ht_rc_list .rc-stay { display: flex; justify-content: space-between; align-items: center; gap: 10px; width: 100%; text-align: left; border: 1px solid #eaecf0;
    border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; background: #fff; min-height: 52px; }
#ht_rc_list .rc-stay:hover, #ht_rc_list .rc-stay:focus { border-color: #1849a9; outline: none; box-shadow: 0 0 0 2px rgba(24,73,169,.15); }
#ht_rc_list .rc-room { font-size: 18px; font-weight: 800; min-width: 54px; }
#ht_rc_banner { border: 2px solid #1849a9; background: #eff4ff; border-radius: 10px; padding: 8px 12px; margin-bottom: 8px; font-weight: 600; }
</style>
@if (! empty($rc_button))
<button type="button" id="ht_rc_btn" aria-haspopup="dialog">&#128719; {{ __('hotel.rcp_btn') }}</button>
@endif
<div class="modal fade" id="ht_rc_modal" tabindex="-1" role="dialog" aria-labelledby="ht_rc_title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ht_rc_title">{{ __('hotel.rcp_title') }}</h4>
            </div>
            <div class="modal-body">
                <input type="search" class="form-control" id="ht_rc_search" placeholder="{{ __('hotel.rcp_search') }}" style="margin-bottom:10px;">
                <div id="ht_rc_list"></div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var URL_IN = @json(action([\App\Http\Controllers\HotelRoomChargeController::class, 'inHouse']));
    var T = @json($rc_T);
    var stays = [];
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

    // The form field travels with the sale; cleared with the form.
    function field() {
        var $f = $('form#add_pos_sell_form'), $i = $f.find('input[name=hotel_charge_reservation_id]');
        if (!$i.length) { $i = $('<input type="hidden" name="hotel_charge_reservation_id">').appendTo($f); }
        return $i;
    }
    window.hotelRoomCharge = {
        // Put the cart on a stay: the guest becomes the customer, then the sale is finished as a credit sale.
        finish: function (stay) {
            if ($('table#pos_table tbody tr.product_row').length === 0) { toastr.warning(T.empty_cart); return; }
            var $c = $('select#customer_id');
            if (!$c.find('option[value="' + stay.contact_id + '"]').length) { $c.append(new Option(stay.guest || ('#' + stay.contact_id), stay.contact_id, true, true)); }
            $c.val(String(stay.contact_id)).trigger('change');
            field().val(stay.id);
            $('#ht_rc_banner').remove();
            $('form#add_pos_sell_form').prepend('<div id="ht_rc_banner">' + esc(T.confirm.replace(':room', stay.room || '—').replace(':guest', stay.guest || '')) +
                ' <a href="#" id="ht_rc_undo" style="margin-left:8px;font-weight:400;">' + esc(T.undo) + '</a></div>');
            var $credit = $('button.pos-express-finalize[data-pay_method="credit_sale"]').first();
            setTimeout(function () {
                if ($credit.length && !$credit.hasClass('hide')) { $credit.trigger('click'); }
                else {
                    toastr.info(T.no_credit);
                    __write_number($('#amount_0'), 0); $('#amount_0').change();
                    $('button#pos-finalize').first().trigger('click');
                }
            }, 300);
        },
        clear: function () { field().val(''); $('#ht_rc_banner').remove(); }
    };

    function render(q) {
        q = (q || '').toLowerCase();
        var list = stays.filter(function (s) { return !q || String(s.room).toLowerCase().indexOf(q) >= 0 || String(s.guest || '').toLowerCase().indexOf(q) >= 0; });
        $('#ht_rc_list').html(list.length ? list.map(function (s) {
            return '<button type="button" class="rc-stay" data-id="' + s.id + '"><span class="rc-room">' + esc(s.room || '—') + '</span>' +
                '<span style="flex:1;"><b>' + esc(s.guest) + '</b><br><small class="text-muted">' + esc(s.ref_no) + ' · ' + esc(T.until) + ' ' + esc(moment(s.departure).format('DD/MM')) + '</small></span>' +
                '<span class="btn btn-primary btn-sm">' + esc(T.pick) + '</span></button>';
        }).join('') : '<p class="text-muted">' + esc(T.none) + '</p>');
    }

    $(document).on('click', '#ht_rc_btn', function () {
        if ($('table#pos_table tbody tr.product_row').length === 0) { toastr.warning(T.empty_cart); return; }
        $('#ht_rc_search').val('');
        $('#ht_rc_list').html('…');
        $('#ht_rc_modal').modal('show');
        $.getJSON(URL_IN).done(function (r) { stays = r.stays || []; render(''); });
    });
    $(document).on('input', '#ht_rc_search', function () { render(this.value); });
    $(document).on('click', '#ht_rc_list .rc-stay', function () {
        var id = parseInt($(this).attr('data-id'), 10);
        var stay = stays.filter(function (s) { return s.id === id; })[0];
        $('#ht_rc_modal').modal('hide');
        if (stay) { window.hotelRoomCharge.finish(stay); }
    });
    $(document).on('click', '#ht_rc_undo', function (e) { e.preventDefault(); window.hotelRoomCharge.clear(); });
    $(document).ajaxSuccess(function (e, xhr, settings) {
        var r = xhr && xhr.responseJSON;
        if (!r || (settings.type || '').toUpperCase() !== 'POST') { return; }
        if (r.room_charge) { toastr.success(r.room_charge); }
        if (r.success === 0 && field().val()) { window.hotelRoomCharge.clear(); } // refused: the next sale is not a room charge
    });
    if (typeof window.reset_pos_form === 'function') {
        var orig = window.reset_pos_form;
        window.reset_pos_form = function () { window.hotelRoomCharge.clear(); return orig.apply(this, arguments); };
    }
})();
</script>
