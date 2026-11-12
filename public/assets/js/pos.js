/*
 * BillTrix POS. Keeps the cart in the browser, prices it with the same rules as
 * the server (InvoiceService), and posts one sale invoice per bill.
 * Keys: F2 scan · F6 recent bills · F7 hold · F8 recall · F9 pay · Esc clear · +/- on selected line
 */
(function ($) {
    'use strict';

    const BT = window.BT, P = window.POS;
    const t = (k) => (P.t && P.t[k]) || k;
    let cart = [];                 // {product_id, name, units, unit_id, factor, qty, price, disc, tax_rate, avg_cost, stock, base_sale, unit_sale}
    let sel = -1;                  // selected line index
    let party = P.walkIn;          // {id, text, walk_in, price_level_id, credit_limit}
    let discMode = 'amt';
    const payModal = new bootstrap.Modal('#payModal');
    const recentModal = new bootstrap.Modal('#recentModal');
    const heldModal = new bootstrap.Modal('#heldModal');

    // ── helpers ───────────────────────────────────────────────────────────
    const toast = (msg, kind = 'danger') => {
        $('#toast').attr('class', `toast align-items-center border-0 text-bg-${kind}`);
        $('#toast-body').text(msg);
        bootstrap.Toast.getOrCreateInstance('#toast', { delay: 2500 }).show();
    };
    const beep = (ok = true) => {
        try {
            const c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator();
            o.frequency.value = ok ? 880 : 220; o.connect(c.destination); o.start(); o.stop(c.currentTime + 0.08);
        } catch (e) { /* no audio */ }
    };
    const unitOf = (l) => l.units.find((u) => +u.unit_id === +l.unit_id) || { factor: 1 };
    const unitPrice = (p, unit) => (unit.sale_price !== null && unit.sale_price !== undefined) ? +unit.sale_price : +p.sale_price * (+unit.factor || 1);

    function lineCalc(l) {
        const gross = l.qty * l.price;
        const disc = BT.round(gross * Math.min(Math.max(l.disc, 0), 100) / 100);
        const taxable = gross - disc;
        const rate = P.tax ? l.tax_rate : 0;
        const tax = P.inclusive ? BT.round(taxable * rate / (100 + rate)) : BT.round(taxable * rate / 100);
        const amount = BT.round(P.inclusive ? taxable : taxable + tax);
        return { gross, disc, tax, amount, net: amount - tax };
    }

    function totals() {
        let gross = 0, idisc = 0, tax = 0, lines = 0, qty = 0;
        cart.forEach((l) => { const c = lineCalc(l); gross += c.gross; idisc += c.disc; tax += c.tax; lines += c.amount; qty += l.qty; });
        let disc = Math.max(BT.num($('#disc').val()), 0);
        if (discMode === 'pct') disc = BT.round(lines * Math.min(disc, 100) / 100);
        const total = BT.round(Math.max(lines - disc, 0));
        return { gross, idisc, tax, disc, total, qty };
    }

    // ── cart ──────────────────────────────────────────────────────────────
    function add(p, qty = 1) {
        const unitId = p.unit_id || p.base_unit_id;
        const i = cart.findIndex((l) => l.product_id === p.id && +l.unit_id === +unitId);
        if (i >= 0) {
            cart[i].qty = BT.round(cart[i].qty + qty, 4);
            sel = i;
        } else {
            const unit = p.units.find((u) => +u.unit_id === +unitId) || { factor: 1 };
            cart.push({
                product_id: p.id, name: p.name, units: p.units, unit_id: unitId, qty,
                price: BT.round(unitPrice(p, unit), 4), disc: 0, tax_rate: P.tax ? (p.tax_rate || 0) : 0,
                avg_cost: p.avg_cost || 0, stock: p.stock, base_sale: p.sale_price,
            });
            sel = cart.length - 1;
        }
        beep(true);
        render();
    }

    function render() {
        const $c = $('#cart').empty();
        if (!cart.length) {
            $c.append(`<div class="empty text-center text-muted py-5"><i class="fas fa-shopping-basket fa-3x mb-3 opacity-25"></i><div>${t('Scan or tap a product to start')}</div></div>`);
        }
        cart.forEach((l, i) => {
            const c = lineCalc(l), unit = unitOf(l), factor = +unit.factor || 1;
            const below = P.belowCost && l.avg_cost > 0 && l.qty > 0 && c.net / (l.qty * factor) + 0.0001 < l.avg_cost;
            const short = l.stock !== null && l.stock !== undefined && l.qty * factor > l.stock + 0.00001;
            const units = l.units.length > 1
                ? `<select class="form-select form-select-sm w-auto" data-unit>${l.units.map((u) => `<option value="${u.unit_id}" ${+u.unit_id === +l.unit_id ? 'selected' : ''}>${BT.esc(u.name)}</option>`).join('')}</select>`
                : `<span class="text-muted small">${BT.esc(unit.name || '')}</span>`;
            $c.append(`<div class="line ${i === sel ? 'sel' : ''} ${below || short ? 'warn' : ''}" data-i="${i}">
                <div class="nm">${BT.esc(l.name)}</div><div class="amt">${BT.money(c.amount)}</div>
                <div class="ctrl">
                    <div class="qty"><button class="btn btn-outline-secondary" data-dec>−</button><input type="number" step="any" min="0" class="form-control" data-qty value="${l.qty}"><button class="btn btn-outline-secondary" data-inc>+</button></div>
                    ${units}
                    <span class="small text-muted">@</span><input type="number" step="any" min="0" class="form-control" style="width:90px" data-price value="${l.price}">
                    <input type="number" step="any" min="0" max="100" class="form-control" style="width:62px" data-disc value="${l.disc || ''}" placeholder="${t('% off')}" title="Line discount %">
                    <button class="btn btn-link text-danger p-0 ms-auto" data-del title="${t('Remove')}"><i class="fas fa-times"></i></button>
                </div>
                ${below ? `<div class="msg">${t('Below cost')} (${BT.money(l.avg_cost * factor)})</div>` : ''}${short ? `<div class="msg">${t('Only')} ${BT.qty(l.stock)} ${t('in stock')}</div>` : ''}
            </div>`);
        });
        const tt = totals();
        $('#t-count').text(`${cart.length} ${t('lines')} · ${BT.qty(tt.qty)} ${t('qty')}`);
        $('#t-gross').text(BT.money(tt.gross));
        $('#t-idisc').text(BT.money(tt.idisc));
        $('#t-tax').text(BT.money(tt.tax));
        $('#t-total').text(BT.money(tt.total));
        $('#btn-pay').prop('disabled', !cart.length);
        const $sel = $c.find('.line.sel');
        if ($sel.length) $sel[0].scrollIntoView({ block: 'nearest' });
    }

    $('#cart')
        .on('click', '.line', function (e) { if (!$(e.target).is('input,select,button,i')) { sel = +$(this).data('i'); render(); } })
        .on('click', '[data-inc],[data-dec]', function () {
            const i = +$(this).closest('.line').data('i');
            cart[i].qty = Math.max(BT.round(cart[i].qty + ($(this).is('[data-inc]') ? 1 : -1), 4), 0);
            if (cart[i].qty === 0) cart.splice(i, 1);
            sel = Math.min(i, cart.length - 1); render();
        })
        .on('click', '[data-del]', function () { cart.splice(+$(this).closest('.line').data('i'), 1); sel = cart.length - 1; render(); })
        .on('change', '[data-qty],[data-price],[data-disc]', function () {
            const i = +$(this).closest('.line').data('i');
            if ($(this).is('[data-qty]')) cart[i].qty = Math.max(BT.num(this.value), 0);
            if ($(this).is('[data-price]')) cart[i].price = Math.max(BT.num(this.value), 0);
            if ($(this).is('[data-disc]')) cart[i].disc = Math.min(Math.max(BT.num(this.value), 0), 100);
            if (cart[i].qty === 0) cart.splice(i, 1);
            render();
        })
        .on('change', '[data-unit]', function () {
            const i = +$(this).closest('.line').data('i'), l = cart[i];
            l.unit_id = +this.value;
            const u = unitOf(l);
            l.price = BT.round(u.sale_price !== null && u.sale_price !== undefined ? +u.sale_price : +l.base_sale * (+u.factor || 1), 4);
            render();
        });

    $('#disc').on('input', render);
    $('#disc-pct').on('click', function () { discMode = discMode === 'amt' ? 'pct' : 'amt'; $(this).toggleClass('active btn-secondary text-white', discMode === 'pct'); render(); });

    // ── offline: local catalog, bill queue, sync ──────────────────────────
    // When the connection drops, products come from a copy kept on this device and
    // bills are queued here with their own id; they're sent (once) when it returns.
    const store = {
        get(k, d) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } },
    };
    let catalog = (store.get(P.catalogKey, {}) || {}).products || [];
    const offline = () => !navigator.onLine;
    const uuid = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); });
    const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };

    function refreshCatalog() {
        if (offline() || !P.catalogUrl) return;
        $.getJSON(P.catalogUrl, { warehouse_id: P.warehouseId }, (d) => {
            catalog = d.products || [];
            if (!store.set(P.catalogKey, d)) toast(t('This device is low on storage; offline selling may be limited.'), 'warning');
        });
    }
    function localSearch(q, cat) {
        const needle = (q || '').toLowerCase();
        return catalog.filter((p) => (!cat || +p.category_id === +cat)
            && (!needle || p.name.toLowerCase().includes(needle) || (p.sku || '').toLowerCase() === needle || p.barcode === q)).slice(0, 60);
    }
    function localBarcode(code) {
        for (const p of catalog) {
            if (p.barcode === code || (p.sku && p.sku === code)) return { ...p, unit_id: p.base_unit_id };
            const u = (p.units || []).find((x) => x.barcode === code);
            if (u) return { ...p, unit_id: u.unit_id };
        }
        return null;
    }

    const queue = () => store.get(P.queueKey, []);
    function setQueue(list) { store.set(P.queueKey, list); showNet(); }
    function showNet() {
        const q = queue(), waiting = q.filter((b) => !b.error).length, failed = q.filter((b) => b.error).length;
        $('#net-offline').toggleClass('d-none', !offline());
        $('#btn-queue').toggleClass('d-none', !q.length).toggleClass('btn-warning', !failed).toggleClass('btn-danger', !!failed)
            .find('span').text(failed ? `${waiting} ${t('to send')}, ${failed} ${t('need attention')}` : `${waiting} ${t('to send')}`);
    }

    let syncing = false;
    async function sync() {
        if (syncing || offline()) return;
        syncing = true;
        try {
            for (const bill of queue().filter((b) => !b.error)) {
                try {
                    const r = await $.ajax({ url: P.storeUrl, method: 'POST', contentType: 'application/json', data: JSON.stringify(bill.body), timeout: 20000 });
                    setQueue(queue().filter((b) => b.body.client_uuid !== bill.body.client_uuid));
                    toast(`${t('Offline bill sent')}: ${r.number}`, 'success');
                } catch (x) {
                    if (!x.status || x.status >= 500) break;            // still no connection — try later
                    if (x.status === 419 || x.status === 401) { toast(t('Your session expired. Reload the POS to send waiting bills.'), 'warning'); break; }
                    const msg = x.responseJSON?.errors ? Object.values(x.responseJSON.errors).flat().join(' ') : (x.responseJSON?.message || `HTTP ${x.status}`);
                    setQueue(queue().map((b) => b.body.client_uuid === bill.body.client_uuid ? { ...b, error: msg } : b));
                }
            }
        } finally {
            syncing = false;
            showNet();
        }
    }
    window.addEventListener('online', () => { showNet(); sync(); refreshCatalog(); });
    window.addEventListener('offline', showNet);
    setInterval(sync, 20000);

    $('#btn-queue').on('click', () => {
        const $l = $('#queue-list').empty();
        queue().forEach((b) => $l.append(`<div class="list-group-item">
            <div class="d-flex justify-content-between"><strong>${BT.esc(b.at)}</strong><strong>${BT.money(b.total)}</strong></div>
            <div class="small text-muted">${BT.esc(b.party)} · ${b.body.items.length} ${t('lines')}</div>
            ${b.error ? `<div class="small text-danger mt-1">${BT.esc(b.error)}</div>
                <button class="btn btn-sm btn-outline-danger mt-1" data-drop="${b.body.client_uuid}">${t('Remove this bill')}</button>` : `<div class="small text-warning">${t('Waiting for connection')}</div>`}
        </div>`));
        new bootstrap.Modal('#queueModal').show();
    });
    $('#queue-list').on('click', '[data-drop]', function () {
        if (!confirm(t('Remove this bill? It will not be recorded.'))) return;
        setQueue(queue().filter((b) => b.body.client_uuid !== $(this).data('drop')));
        $(this).closest('.list-group-item').remove();
    });
    $('#btn-sync').on('click', sync);

    /** Receipt printed straight from the browser for a bill saved offline. */
    function printLocal(bill, change) {
        const rows = bill.body.items.map((i) => {
            const l = bill.lines.find((x) => x.product_id === i.product_id && +x.unit_id === +i.unit_id) || {};
            return `<tr><td colspan="2">${BT.esc(l.name || '')}</td></tr><tr><td>${BT.qty(i.quantity)} x ${BT.money(i.price)}${i.discount_percent ? ' -' + i.discount_percent + '%' : ''}</td><td class="r">${BT.money(lineCalc({ qty: i.quantity, price: i.price, disc: i.discount_percent || 0, tax_rate: i.tax_rate || 0 }).amount)}</td></tr>`;
        }).join('');
        const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            @page{size:80mm auto;margin:0}body{font-family:"Courier New",monospace;font-size:12px;width:74mm;margin:0 auto;padding:3mm 0}
            .c{text-align:center}.r{text-align:right}hr{border:0;border-top:1px dashed #000}table{width:100%;border-collapse:collapse}.b{font-weight:bold;font-size:14px}</style></head>
            <body onload="window.print()"><div class="c b">${BT.esc(P.businessName)}</div><div class="c">${t('PROVISIONAL RECEIPT — OFFLINE')}</div><hr>
            <div>${BT.esc(bill.at)} · ${BT.esc(bill.party)}</div><div>${t('Ref')}: ${bill.body.client_uuid.slice(0, 8).toUpperCase()}</div><hr>
            <table>${rows}</table><hr><table>
            ${bill.body.discount ? `<tr><td>${t('Discount')}</td><td class="r">-${BT.money(bill.body.discount)}</td></tr>` : ''}
            <tr class="b"><td>${t('TOTAL')}</td><td class="r">${BT.money(bill.total)}</td></tr>
            <tr><td>${t('Paid')}</td><td class="r">${BT.money(bill.paid + change)}</td></tr>
            ${change > 0 ? `<tr><td>${t('Change')}</td><td class="r">${BT.money(change)}</td></tr>` : ''}</table><hr>
            <div class="c">${t('Bill number will be issued when this device is back online.')}</div></body></html>`;
        document.getElementById('print-frame').srcdoc = html;
    }

    // ── products: grid, search, scanner ───────────────────────────────────
    function tiles(list) {
        const $g = $('#grid').empty();
        if (!list.length) { $g.append(`<div class="text-muted p-4">${t('No products found.')}</div>`); return; }
        list.forEach((p) => {
            const out = p.stock !== null && p.stock !== undefined && p.stock <= 0;
            const unit = p.units.find((u) => +u.unit_id === +(p.unit_id || p.base_unit_id)) || { factor: 1, name: '' };
            $g.append($(`<button class="tile ${out ? 'out' : ''}">
                ${p.image ? `<img src="${BT.esc(p.image)}" alt="">` : ''}
                <span class="nm">${BT.esc(p.name)}</span>
                <span><span class="pr">${BT.money(unitPrice(p, unit))}</span> <span class="st">${p.stock === null || p.stock === undefined ? '' : BT.qty(p.stock) + ' ' + BT.esc(unit.name)}</span></span>
            </button>`).data('p', p));
        });
    }
    $('#grid').on('click', '.tile', function () { add($(this).data('p')); $('#scan').trigger('focus'); });

    let searchTimer;
    function search(q, cat) {
        clearTimeout(searchTimer);
        if (!q && !cat) { tiles(P.quick); return; }
        if (offline()) { tiles(localSearch(q, cat)); return; }
        searchTimer = setTimeout(() => {
            $.getJSON(BT.urls.products, { q, category_id: cat || '', warehouse_id: P.warehouseId, party_id: party?.id }, (d) => {
                if ($.trim($('#scan').val()) === q) tiles(d.results); // ignore answers to an older keystroke
            }).fail(() => tiles(localSearch(q, cat)));
        }, q ? 180 : 0);
    }
    $('.chip').on('click', function () { $('.chip').removeClass('active'); $(this).addClass('active'); $('#scan').val(''); search('', $(this).data('cat')); });
    $('#scan').on('input', function () { search($.trim(this.value), ''); });
    $('#clear-search').on('click', () => { $('#scan').val('').trigger('focus'); tiles(P.quick); });

    // Enter = treat as barcode/SKU (supports "3*barcode"); falls back to the first search hit.
    $('#scan').on('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        clearTimeout(searchTimer);
        let code = $.trim(this.value), qty = 1;
        if (!code) { if (cart.length) openPay(); return; }
        const m = code.match(/^(\d+(?:\.\d+)?)\*(.+)$/);
        if (m) { qty = +m[1]; code = m[2]; }
        const fromDevice = () => {
            const p = localBarcode(code);
            if (p) { add(p, qty); $('#scan').val(''); tiles(P.quick); return; }
            beep(false); toast(`${t('No product with code')} "${code}"`);
        };
        if (offline()) { fromDevice(); return; }
        $.getJSON(BT.urls.products, { q: code, barcode: 1, warehouse_id: P.warehouseId, party_id: party?.id }, (d) => {
            if (d.results.length) { add(d.results[0], qty); $('#scan').val(''); tiles(P.quick); return; }
            const first = $('#grid .tile').first();
            if (first.length && $('#grid .tile').length === 1) { add(first.data('p'), qty); $('#scan').val(''); tiles(P.quick); return; }
            beep(false); toast(`${t('No product with code')} "${code}"`);
        }).fail(fromDevice);
    });

    // ── customer ──────────────────────────────────────────────────────────
    const $party = $('#party');
    BT.partySelect($party);
    function setParty(p) {
        party = p;
        $party.empty().append(new Option(p.text, p.id, true, true)).trigger('change');
        $('#walkin-fields').toggle(!!p.walk_in);
        // Re-price lines for the new customer's price level / special prices.
        if (cart.length && !offline()) {
            $.getJSON(BT.urls.products, { ids: cart.map((l) => l.product_id).join(','), party_id: p.id, warehouse_id: P.warehouseId }, (d) => {
                cart.forEach((l) => {
                    const fresh = d.results.find((r) => r.id === l.product_id);
                    if (!fresh) return;
                    l.base_sale = fresh.sale_price; l.units = fresh.units;
                    l.price = BT.round(unitPrice(fresh, unitOf(l)), 4);
                });
                render();
            });
        }
    }
    if (P.walkIn) setParty(P.walkIn);
    $party.on('select2:select', (e) => setParty(e.params.data));
    $party.on('select2:clear', () => P.walkIn && setParty(P.walkIn));
    $('#walkin-btn').on('click', () => P.walkIn && setParty(P.walkIn));

    // ── hold / recall (kept in this browser) ──────────────────────────────
    const held = () => { try { return JSON.parse(localStorage.getItem(P.holdKey) || '[]'); } catch (e) { return []; } };
    const saveHeld = (list) => { try { localStorage.setItem(P.holdKey, JSON.stringify(list)); } catch (e) { /* storage unavailable */ } showHeld(); };
    const showHeld = () => { const n = held().length; $('#held-count').text(n).toggleClass('d-none', !n); };
    function clearBill() { cart = []; sel = -1; $('#disc').val(0); $('#cust-name,#cust-phone').val(''); if (P.walkIn) setParty(P.walkIn); render(); $('#scan').trigger('focus'); }
    $('#btn-hold').on('click', () => {
        if (!cart.length) return;
        saveHeld([...held(), { at: new Date().toLocaleTimeString(), cart, party, disc: $('#disc').val(), name: $('#cust-name').val(), phone: $('#cust-phone').val(), total: totals().total }]);
        clearBill(); toast(t('Bill held'), 'secondary');
    });
    $('#btn-recall').on('click', () => {
        const list = held(), $l = $('#held-list').empty();
        if (!list.length) { $l.append(`<div class="p-3 text-muted">${t('No held bills.')}</div>`); }
        list.forEach((h, i) => $l.append(`<button class="list-group-item list-group-item-action d-flex justify-content-between" data-i="${i}">
            <span>${BT.esc(h.at)} · ${BT.esc(h.party?.text || '')} · ${h.cart.length} lines</span><strong>${BT.money(h.total)}</strong></button>`));
        heldModal.show();
    });
    $('#held-list').on('click', '[data-i]', function () {
        if (cart.length && !confirm(t('Replace the current bill?'))) return;
        const list = held(), h = list.splice(+$(this).data('i'), 1)[0];
        saveHeld(list);
        cart = h.cart; sel = cart.length - 1; $('#disc').val(h.disc || 0);
        setParty(h.party || P.walkIn); $('#cust-name').val(h.name || ''); $('#cust-phone').val(h.phone || '');
        render(); heldModal.hide();
    });
    $('#btn-clear').on('click', () => { if (!cart.length || confirm(t('Clear this bill?'))) clearBill(); });

    // ── payment ───────────────────────────────────────────────────────────
    function openPay() {
        if (!cart.length) return;
        const tt = totals();
        $('#pay-total').text(BT.money(tt.total));
        $('.pay-amt').val('');
        $('.pay-amt[data-cash=1]').first().val(tt.total.toFixed(2));
        $('#pay-error').addClass('d-none');
        payCalc();
        payModal.show();
        setTimeout(() => $('.pay-amt').first().trigger('focus').trigger('select'), 300);
    }
    function payCalc() {
        const total = totals().total;
        let sum = 0, nonCash = 0;
        $('.pay-amt').each(function () { const v = BT.num(this.value); sum += v; if (+$(this).data('cash') !== 1) nonCash += v; });
        const due = BT.round(total - sum);
        // Change only comes out of cash.
        const change = Math.max(-due, 0);
        $('#pay-sum').text(BT.money(sum));
        $('#pay-due').text(BT.money(Math.max(due, 0))); $('#pay-due-row').toggle(due > 0);
        $('#pay-change').text(BT.money(change)); $('#pay-change-row').toggle(change > 0);
        const credit = due > 0;
        $('#credit-note').toggleClass('d-none', !credit).text(party?.walk_in
            ? t('Walk-in bills must be paid in full. Pick a customer to sell on credit.')
            : `${BT.money(due)} — ${party?.text || ''}: ${t('will go on the customer\'s account.')}`);
        $('#btn-confirm').prop('disabled', (credit && (party?.walk_in || !P.creditSales)) || nonCash > total + 0.001);
        return { total, sum, due, change };
    }
    $(document).on('input', '.pay-amt', payCalc);
    $(document).on('click', '.pay-rest', function () {
        const $in = $(this).siblings('.pay-amt'); $in.val('');
        const c = payCalc(); $in.val(Math.max(c.due, 0).toFixed(2)); payCalc();
    });
    $('#quick-cash').on('click', '[data-add]', function () {
        const $cash = $('.pay-amt[data-cash=1]').first(), add = $(this).data('add');
        if (add === 'exact') { $('.pay-amt').val(''); $cash.val(totals().total.toFixed(2)); }
        else $cash.val(BT.round(BT.num($cash.val()) + +add).toFixed(2));
        payCalc();
    });
    $('#btn-pay').on('click', openPay);

    let saving = false;
    function confirmSale() {
        if (saving) return;
        const c = payCalc();
        if ($('#btn-confirm').prop('disabled')) return;
        // Record only what settles the bill: the change handed back reduces cash.
        let changeLeft = c.change;
        const payments = [];
        $('.pay-amt').each(function () {
            let v = BT.num(this.value);
            if (+$(this).data('cash') === 1 && changeLeft > 0) { const take = Math.min(v, changeLeft); v -= take; changeLeft -= take; }
            if (v > 0) payments.push({ account_id: $(this).data('account'), amount: BT.round(v) });
        });
        const tt = totals();
        const body = {
            party_id: party.id, warehouse_id: P.warehouseId,
            customer_name: party.walk_in ? $('#cust-name').val() : null, customer_phone: party.walk_in ? $('#cust-phone').val() : null,
            discount: tt.disc,
            items: cart.map((l) => ({ product_id: l.product_id, unit_id: l.unit_id, quantity: l.qty, price: l.price, discount_percent: l.disc, tax_rate: l.tax_rate })),
            payments,
            client_uuid: uuid(),
            sold_at: today(),
        };
        const keepOffline = () => {
            const bill = { at: new Date().toLocaleString(), party: party.text, total: tt.total, paid: payments.reduce((a, p) => a + p.amount, 0),
                body, lines: cart.map((l) => ({ product_id: l.product_id, unit_id: l.unit_id, name: l.name })) };
            setQueue([...queue(), bill]);
            payModal.hide();
            toast(t('No connection — bill saved on this device and will be sent automatically.'), 'warning');
            if ($('#auto-print').is(':checked')) printLocal(bill, c.change);
            clearBill();
        };
        if (offline()) { keepOffline(); return; }
        saving = true; $('#btn-confirm').prop('disabled', true).html(`<span class="spinner-border spinner-border-sm"></span> ${t('Saving…')}`);
        $.ajax({ url: P.storeUrl, method: 'POST', contentType: 'application/json', data: JSON.stringify(body) })
            .done((r) => {
                payModal.hide();
                toast(`${r.number} ${t('saved')} · ${BT.money(r.total)}${c.change > 0 ? ' · ' + t('Change') + ' ' + BT.money(c.change) : ''}`, 'success');
                if ($('#auto-print').is(':checked')) $('#print-frame').attr('src', r.print);
                clearBill();
            })
            .fail((x) => {
                if (!x.status) { keepOffline(); return; }        // the connection dropped mid-save
                const errs = x.responseJSON?.errors ? Object.values(x.responseJSON.errors).flat() : [x.responseJSON?.message || t('Could not save the sale.')];
                $('#pay-error').removeClass('d-none').html(errs.map(BT.esc).join('<br>'));
                beep(false);
            })
            .always(() => { saving = false; $('#btn-confirm').prop('disabled', false).html(`<i class="fas fa-check"></i> ${t('Complete sale')} <small>(Enter)</small>`); });
    }
    $('#btn-confirm').on('click', confirmSale);
    $('#payModal').on('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); confirmSale(); } });
    $('#payModal').on('hidden.bs.modal', () => $('#scan').trigger('focus'));

    // ── recent bills ──────────────────────────────────────────────────────
    function loadRecent(q = '') {
        $.getJSON(P.recentUrl, { q }, (list) => {
            const $l = $('#recent-list').empty();
            if (!list.length) $l.append(`<div class="p-3 text-muted">${t('No bills yet.')}</div>`);
            list.forEach((b) => $l.append(`<div class="list-group-item d-flex justify-content-between align-items-center">
                <div><strong>${BT.esc(b.number)}</strong> <span class="small text-muted">${b.time} · ${BT.esc(b.customer)}</span></div>
                <div class="d-flex gap-2 align-items-center"><strong>${BT.money(b.total)}</strong>
                    <button class="btn btn-sm btn-outline-success" data-print="${BT.esc(b.print)}" title="${t('Reprint')}"><i class="fas fa-print"></i></button>
                    ${b.return ? `<a class="btn btn-sm btn-outline-warning" href="${BT.esc(b.return)}" target="_blank" title="${t('Return / exchange')}"><i class="fas fa-undo"></i></a>` : ''}</div></div>`));
        });
    }
    $('#btn-recent').on('click', () => { loadRecent(); recentModal.show(); });
    $('#recent-q').on('input', function () { loadRecent(this.value); });
    $('#recent-list').on('click', '[data-print]', function () { $('#print-frame').attr('src', $(this).data('print')); });

    // ── keyboard ──────────────────────────────────────────────────────────
    $(document).on('keydown', (e) => {
        if ($('.modal.show').length) return;
        const typing = $(e.target).is('input,select,textarea') && e.target.id !== 'scan';
        if (e.key === 'F2') { e.preventDefault(); $('#scan').trigger('focus').trigger('select'); }
        else if (e.key === 'F6') { e.preventDefault(); $('#btn-recent').trigger('click'); }
        else if (e.key === 'F7') { e.preventDefault(); $('#btn-hold').trigger('click'); }
        else if (e.key === 'F8') { e.preventDefault(); $('#btn-recall').trigger('click'); }
        else if (e.key === 'F9') { e.preventDefault(); openPay(); }
        else if (e.key === 'Escape') { $('#btn-clear').trigger('click'); }
        else if (!typing && sel >= 0 && (e.key === '+' || e.key === '-') && $('#scan').val() === '') {
            e.preventDefault();
            cart[sel].qty = Math.max(cart[sel].qty + (e.key === '+' ? 1 : -1), 0);
            if (!cart[sel].qty) { cart.splice(sel, 1); sel = cart.length - 1; }
            render();
        } else if (!typing && sel >= 0 && e.key === 'Delete') { cart.splice(sel, 1); sel = cart.length - 1; render(); }
    });

    window.addEventListener('beforeunload', (e) => { if (cart.length) { e.preventDefault(); e.returnValue = ''; } });

    tiles(P.quick);
    showHeld();
    render();
    showNet();
    refreshCatalog();
    setInterval(refreshCatalog, 10 * 60 * 1000);
    sync();

    // Keep this page available offline.
    if ('serviceWorker' in navigator && P.swUrl) {
        navigator.serviceWorker.register(P.swUrl).then((reg) => {
            const send = () => (reg.active || navigator.serviceWorker.controller)?.postMessage({ type: 'cache-page', url: location.href });
            if (reg.active) send(); else navigator.serviceWorker.ready.then(send);
        }).catch(() => {});
    }
})(jQuery);
