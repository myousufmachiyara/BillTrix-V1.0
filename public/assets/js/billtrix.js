/* BillTrix common UI behaviour */
(function ($) {
    'use strict';

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' } });

    const BT = window.BT = window.BT || {};

    BT.num = (v) => { const n = parseFloat(String(v).replace(/,/g, '')); return isFinite(n) ? n : 0; };
    BT.round = (v, d = 2) => { const f = Math.pow(10, d); return Math.round((v + Number.EPSILON) * f) / f; };
    BT.money = (v) => BT.round(v).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    BT.qty = (v) => BT.round(v, 4).toLocaleString('en-PK', { maximumFractionDigits: 4 });
    BT.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    BT.partySelect = function (el) {
        const $el = $(el);
        $el.select2({
            theme: 'bootstrap',
            placeholder: $el.data('placeholder') || 'Search…',
            allowClear: !$el.prop('required'),
            minimumInputLength: 0,
            ajax: {
                url: BT.urls.parties, delay: 200,
                data: (p) => ({ q: p.term, type: $el.data('type') || '' }),
                processResults: (d) => d,
            },
        });
    };

    BT.productSelect = function (el, extra) {
        const $el = $(el);
        $el.select2({
            theme: 'bootstrap',
            placeholder: $el.data('placeholder') || 'Search product…',
            allowClear: true,
            ajax: {
                url: BT.urls.products, delay: 150,
                data: (p) => Object.assign({ q: p.term }, typeof extra === 'function' ? extra() : {}),
                processResults: (d) => d,
            },
            templateResult: (p) => {
                if (!p.id) return p.text;
                const stock = p.stock === null || p.stock === undefined ? '' : ` <span class="badge bg-${p.stock > 0 ? 'light text-dark' : 'danger'} ms-1">${BT.qty(p.stock)}</span>`;
                return $(`<span>${BT.esc(p.text)}${stock}</span>`);
            },
        });
    };

    $(function () {
        $('[data-party-select]').each((_, el) => BT.partySelect(el));
        $('[data-product-select]').each((_, el) => BT.productSelect(el));
        $('select.select2').select2({ theme: 'bootstrap' });

        // Prevent double submits.
        $(document).on('submit', 'form', function () {
            const $btn = $(this).find('button[type=submit]:not([formaction])');
            setTimeout(() => $btn.prop('disabled', true), 0);
            setTimeout(() => $btn.prop('disabled', false), 4000);
        });

        // Clickable table rows.
        $(document).on('click', 'tr[data-href]', function (e) {
            if ($(e.target).closest('a,button,form,input').length) return;
            window.location = $(this).data('href');
        });

        // Magnific popup modals (change password etc.)
        if ($.fn.magnificPopup) {
            $('.modal-with-zoom-anim').magnificPopup({ type: 'inline', fixedContentPos: false, fixedBgPos: true, overflowY: 'auto', closeBtnInside: true, preloader: false, midClick: true, removalDelay: 300, mainClass: 'my-mfp-zoom-in', modal: true });
            $(document).on('click', '.modal-dismiss', (e) => { e.preventDefault(); $.magnificPopup.close(); });
        }
    });
})(jQuery);
