{{-- PosInnova bulk items: "finished" / "start" / "undo" on the cards (page and POS window) --}}
<script>
    window.BULK = {
        base: @json(url('bulk-items')),
        text: {!! json_encode([
            'finish_title' => __('bulk.finish_title'), 'finish_text' => __('bulk.finish_text'), 'how_many' => __('bulk.how_many'),
            'none_left' => __('bulk.none_left'), 'note' => __('bulk.note_label'), 'confirm' => __('bulk.confirm_btn'), 'cancel' => __('messages.cancel'),
            'start_title' => __('bulk.start_title'), 'start_text' => __('bulk.start_text'), 'undo_title' => __('bulk.undo_title'), 'undo_text' => __('bulk.undo_text'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
    };
    document.addEventListener('DOMContentLoaded', function () {
        var B = window.BULK, T = B.text;
        var fill = function (s, v) { return String(s || '').replace(/:(\w+)/g, function (m, k) { return v[k] != null ? v[k] : m; }); };
        var token = function () { return $('meta[name=csrf-token]').attr('content'); };
        var refresh = function ($cards) {
            var loc = $cards.data('location');
            var $wrap = $cards.parent();
            $.get(B.base + '/pos-panel', { location_id: loc, compact: $cards.hasClass('bk-compact') ? '1' : '0' }).done(function (html) { $wrap.html(html); });
        };
        var post = function (url, data, $cards) {
            data._token = token();
            return $.post(url, data).done(function (r) {
                if (r.flag === 'low') { toastr.error(r.msg); } else if (r.flag === 'high') { toastr.warning(r.msg); } else { toastr.success(r.msg); }
                refresh($cards);
            }).fail(function (x) {
                toastr.error((x.responseJSON && (x.responseJSON.msg || x.responseJSON.message)) || LANG.something_went_wrong);
            });
        };
        $(document).on('click', '.bk-finish', function () {
            var $b = $(this), $cards = $b.closest('.bk-cards'), v = { label: $b.data('label'), name: $b.data('name') };
            var box = document.createElement('div');
            box.className = 'bk-dialog';
            box.innerHTML = '<label for="bk_d_n"></label><input type="number" id="bk_d_n" min="1" max="20" step="1" value="1">'
                + '<label class="bk-check"><input type="checkbox" id="bk_d_none"> <span></span></label>'
                + '<label for="bk_d_note"></label><textarea id="bk_d_note" rows="2" maxlength="1000"></textarea>';
            box.querySelector('label[for=bk_d_n]').textContent = fill(T.how_many, v);
            box.querySelector('.bk-check span').textContent = fill(T.none_left, v);
            box.querySelector('label[for=bk_d_note]').textContent = T.note;
            swal({ title: fill(T.finish_title, v), text: fill(T.finish_text, v), content: box, buttons: [T.cancel, T.confirm] }).then(function (ok) {
                if (!ok) return;
                post(B.base + '/' + $b.data('id') + '/finish', {
                    location_id: $cards.data('location'), containers: Math.max(1, parseInt(box.querySelector('#bk_d_n').value, 10) || 1),
                    none_left: box.querySelector('#bk_d_none').checked ? 1 : 0, note: box.querySelector('#bk_d_note').value
                }, $cards);
            });
        });
        $(document).on('click', '.bk-start', function () {
            var $b = $(this), $cards = $b.closest('.bk-cards'), v = { label: $b.data('label'), name: $b.data('name') };
            swal({ title: fill(T.start_title, v), text: fill(T.start_text, v), buttons: [T.cancel, T.confirm] }).then(function (ok) {
                if (ok) post(B.base + '/' + $b.data('id') + '/start', { location_id: $cards.data('location') }, $cards);
            });
        });
        $(document).on('click', '.bk-undo', function () {
            var $b = $(this), $cards = $b.closest('.bk-cards');
            swal({ title: T.undo_title, text: T.undo_text, icon: 'warning', buttons: [T.cancel, T.confirm], dangerMode: true }).then(function (ok) {
                if (ok) post(B.base + '/cycles/' + $b.data('cycle') + '/undo', {}, $cards);
            });
        });
        // the POS "Bags" button: the cards of the till's location
        $(document).on('click', '#bk_pos_btn', function () {
            var $m = $('#bk_pos_modal');
            $m.find('.modal-body').html('<div class="text-center" style="padding:20px;"><i class="fas fa-spinner fa-spin"></i></div>');
            $m.modal('show');
            $.get(B.base + '/pos-panel', { location_id: $('#location_id').val(), compact: '1' }).done(function (html) { $m.find('.modal-body').html(html); });
        });
    });
</script>
