<script>
(function () {
    var modal = $('#reviewModal');
    var body = document.getElementById('review-body');
    var confirmBtn = document.getElementById('review-confirm');
    var reasonBox = document.getElementById('review-reason');
    var reasonInput = document.getElementById('review-reason-input');
    var check = document.getElementById('review-check');
    var checkBox = document.getElementById('review-check-box');
    var checkLabel = document.getElementById('review-check-label');
    var T = {!! json_encode(['default' => __('ledger.review_check_default'), 'checking' => __('ledger.checking'), 'confirm' => __('ledger.confirm'), 'failed' => __('ledger.check_failed'), 'forbidden' => __('ledger.not_allowed'), 'working' => __('ledger.working')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var current = null;

    function previewOk() {
        var p = body.querySelector('.ap-preview');
        return !!p && p.getAttribute('data-ok') === '1';
    }
    function refresh() {
        var ok = previewOk();
        var needsReason = current && current.getAttribute('data-needs-reason') === '1';
        check.disabled = !ok;
        if (!ok) { check.checked = false; }
        checkBox.classList.toggle('is-ready', ok);
        confirmBtn.disabled = !ok || !check.checked || (needsReason && reasonInput.value.trim() === '');
    }

    $(document).on('click', '[data-review-url]', function (e) {
        e.preventDefault();
        current = this;
        var formId = this.getAttribute('data-review-form');
        if (formId) {
            var form = document.getElementById(formId);
            if (form.reportValidity && !form.reportValidity()) { return; }
        }
        body.innerHTML = '<div class="ap-muted"></div>';
        body.firstChild.textContent = T.checking;
        confirmBtn.textContent = this.getAttribute('data-confirm-label') || T.confirm;
        confirmBtn.className = 'btn ' + (this.getAttribute('data-confirm-class') || 'btn-success');
        checkLabel.textContent = this.getAttribute('data-confirm-check') || T['default'];
        check.checked = false;
        reasonInput.value = '';
        reasonBox.style.display = this.getAttribute('data-needs-reason') === '1' ? '' : 'none';
        refresh();
        modal.modal('show');

        var url = this.getAttribute('data-review-url');
        var request = formId ? $.post(url, $('#' + formId).serialize()) : $.get(url);
        request.done(function (html) { body.innerHTML = html; refresh(); })
            .fail(function (xhr) {
                var msg = T.failed;
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(function (m) { return m[0]; }).join(' ');
                } else if (xhr.status === 403) {
                    msg = T.forbidden;
                }
                body.innerHTML = '<div class="ap-preview" data-ok="0"><div class="ap-note is-bad">' + $('<div>').text(msg).html() + '</div></div>';
                refresh();
            });
    });

    reasonInput.addEventListener('input', refresh);
    check.addEventListener('change', refresh);

    confirmBtn.addEventListener('click', function () {
        if (confirmBtn.disabled || !current || !check.checked) { return; }
        var form = document.getElementById(current.getAttribute('data-confirm-form'));
        if (current.getAttribute('data-needs-reason') === '1') {
            var r = form.querySelector('[name="reason"]');
            if (!r) { r = document.createElement('input'); r.type = 'hidden'; r.name = 'reason'; form.appendChild(r); }
            r.value = reasonInput.value.trim();
        }
        var flag = form.querySelector('input[name="reviewed"]');
        if (!flag) {
            flag = document.createElement('input');
            flag.type = 'hidden';
            flag.name = 'reviewed';
            form.appendChild(flag);
        }
        flag.value = '1';
        confirmBtn.disabled = true;
        confirmBtn.textContent = T.working;
        form.submit();
    });
})();
</script>
