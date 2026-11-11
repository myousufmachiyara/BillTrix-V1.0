/*
 * BillTrix invoice editor — sales, purchases and returns.
 * Mirrors the server calculation in App\Services\InvoiceService so the screen
 * shows exactly what will be posted. The server always recalculates.
 */
(function ($) {
    'use strict';

    const BT = window.BT;

    BT.invoiceEditor = function (cfg) {
        const $form = $(cfg.form);
        const $tbody = $form.find('#lines tbody');
        const $party = $form.find('[name=party_id]');
        const $warehouse = $form.find('[name=warehouse_id]');
        const isSale = cfg.type === 'sale' || cfg.type === 'sale_return';
        const isReturn = cfg.type.endsWith('_return');
        const fromOriginal = !!cfg.returnOf;
        let partyMeta = cfg.party || {};

        // ── rows ─────────────────────────────────────────────────────────
        function rowHtml(l) {
            const units = (l.units || []).map((u) =>
                `<option value="${u.unit_id}" data-factor="${u.factor}" data-sale="${u.sale_price ?? ''}" data-purchase="${u.purchase_price ?? ''}" ${+u.unit_id === +l.unit_id ? 'selected' : ''}>${BT.esc(u.name)}${u.factor !== 1 ? ' (' + BT.qty(u.factor) + ')' : ''}</option>`
            ).join('');
            const stock = l.stock === null || l.stock === undefined ? '' :
                `<span class="badge stock-badge ${l.stock > 0 ? 'bg-light text-dark' : 'bg-danger'}" title="In stock (base units)">${BT.qty(l.stock)} in stock</span>`;
            const max = l.max_quantity ? `<div class="small text-muted">${l.order_item_id ? 'pending' : 'of'} ${BT.qty(l.max_quantity)}</div>` : '';
            const hidden = ['id', 'order_item_id', 'delivery_item_id'].filter((k) => l[k]).map((k) => `<input type="hidden" data-f="${k}" value="${l[k]}">`).join('');
            const lockQty = fromOriginal || l.lock;
            const note = l.note ? `<span class="badge bg-info-subtle text-dark stock-badge">${BT.esc(l.note)}</span>` : '';

            return `<tr data-avg="${l.avg_cost || 0}" data-base-sale="${l.base_sale ?? ''}" data-base-purchase="${l.base_purchase ?? ''}">
                <td class="col-product">
                    <input type="hidden" data-f="product_id" value="${l.product_id}">
                    <input type="hidden" data-f="source_item_id" value="${l.source_item_id ?? ''}">${hidden}
                    <div class="fw-semibold">${BT.esc(l.name)}</div>${note}${stock}<div class="row-msg small text-warning"></div>
                </td>
                <td ${cfg.multiUnit || (l.units || []).length > 1 ? '' : 'class="d-none"'}><select class="form-select form-select-sm" data-f="unit_id" ${lockQty || l.order_item_id ? 'disabled' : ''}>${units}</select></td>
                <td><input type="number" step="any" min="0" class="form-control form-control-sm text-end" data-f="quantity" value="${l.quantity ?? 1}" ${l.max_quantity ? `max="${l.max_quantity}"` : ''} ${l.lock ? 'readonly' : ''}>${max}</td>
                <td><input type="number" step="any" min="0" class="form-control form-control-sm text-end" data-f="price" value="${BT.round(l.price ?? 0, 4)}" ${fromOriginal ? 'readonly' : ''}></td>
                <td><input type="number" step="any" min="0" max="100" class="form-control form-control-sm text-end" data-f="discount_percent" value="${l.discount_percent || 0}" ${fromOriginal ? 'readonly' : ''}></td>
                <td ${cfg.tax ? '' : 'class="d-none"'}><input type="number" step="any" min="0" max="100" class="form-control form-control-sm text-end" data-f="tax_rate" value="${l.tax_rate || 0}" ${fromOriginal ? 'readonly' : ''}></td>
                <td class="num col-amount" data-amount>0.00</td>
                <td class="text-center">${fromOriginal || l.lock ? '' : '<button type="button" class="btn btn-link text-danger p-0" data-remove title="Remove"><i class="fas fa-times"></i></button>'}</td>
            </tr>`;
        }

        function priceFor(p, unit) {
            const factor = +unit.factor || 1;
            if (isSale) return unit.sale_price !== null && unit.sale_price !== undefined ? +unit.sale_price : +p.sale_price * factor;
            return unit.purchase_price !== null && unit.purchase_price !== undefined ? +unit.purchase_price : +p.purchase_price * factor;
        }

        function addProduct(p, qty) {
            // Same product and unit already on the bill → just add quantity (scanner friendly).
            const unitId = p.unit_id || p.base_unit_id;
            const $existing = $tbody.find('tr').filter(function () {
                return +$(this).find('[data-f=product_id]').val() === +p.id && +$(this).find('[data-f=unit_id]').val() === +unitId;
            }).first();
            if ($existing.length) {
                const $q = $existing.find('[data-f=quantity]');
                $q.val(BT.round(BT.num($q.val()) + (qty || 1), 4)).trigger('input');
                flash($existing);
                return;
            }
            const unit = (p.units || []).find((u) => +u.unit_id === +unitId) || { factor: 1 };
            const $row = $(rowHtml({
                product_id: p.id, name: p.name, units: p.units, unit_id: unitId, quantity: qty || 1,
                price: priceFor(p, unit), discount_percent: 0, tax_rate: cfg.tax ? p.tax_rate : 0,
                avg_cost: p.avg_cost, stock: cfg.type === 'sale' || cfg.type === 'purchase_return' ? p.stock : null,
                base_sale: p.sale_price, base_purchase: p.purchase_price,
            }));
            $tbody.append($row);
            recalc();
            $row.find('[data-f=quantity]').trigger('focus').trigger('select');
        }

        function flash($row) {
            $row.addClass('table-success');
            setTimeout(() => $row.removeClass('table-success'), 400);
        }

        // ── totals (same rules as the server) ──────────────────────────────
        function recalc() {
            let subtotal = 0, itemDisc = 0, tax = 0, linesTotal = 0, count = 0;
            $tbody.find('tr').each(function () {
                const $r = $(this);
                const q = BT.num($r.find('[data-f=quantity]').val());
                const price = BT.num($r.find('[data-f=price]').val());
                const dp = Math.min(Math.max(BT.num($r.find('[data-f=discount_percent]').val()), 0), 100);
                const rate = cfg.tax ? BT.num($r.find('[data-f=tax_rate]').val()) : 0;
                const gross = q * price;
                const disc = BT.round(gross * dp / 100);
                const taxable = gross - disc;
                const t = cfg.inclusive ? BT.round(taxable * rate / (100 + rate)) : BT.round(taxable * rate / 100);
                const amount = BT.round(cfg.inclusive ? taxable : taxable + t);
                $r.find('[data-amount]').text(BT.money(amount));

                // Below-cost hint for sales.
                const factor = +$r.find('[data-f=unit_id] option:selected').data('factor') || 1;
                const avg = +$r.data('avg') || 0;
                const netUnit = q > 0 ? (amount - t) / (q * factor) : 0;
                const warn = cfg.type === 'sale' && avg > 0 && q > 0 && netUnit + 0.0001 < avg;
                $r.toggleClass('row-warn', warn).find('.row-msg').text(warn ? `Below cost (${BT.money(avg * factor)})` : '');

                if (q > 0) count++;
                subtotal += gross; itemDisc += disc; tax += t; linesTotal += amount;
            });

            const discount = Math.max(BT.num($form.find('[name=discount]').val()), 0);
            const other = Math.max(BT.num($form.find('[name=other_charges]').val()), 0);
            const roundOff = BT.num($form.find('[name=round_off]').val());
            const total = BT.round(linesTotal - discount + other + roundOff);

            $form.find('[data-t=subtotal]').text(BT.money(subtotal));
            $form.find('[data-t=item_discount]').text(BT.money(itemDisc));
            $form.find('[data-t=tax]').text(BT.money(tax));
            $form.find('[data-t=total]').text(BT.money(total));
            $form.find('[data-t=count]').text(count);

            const $paid = $form.find('[name=paid_amount]');
            if ($form.find('[name=payment_mode]:checked').val() === 'cash') $paid.val(total.toFixed(2));
            const paid = BT.num($paid.val());
            const due = BT.round(total - paid);
            $form.find('[data-t=due]').text(BT.money(Math.max(due, 0)));
            $form.find('[data-t=change]').text(BT.money(Math.max(-due, 0)));
            $form.find('.change-row').toggleClass('d-none', due >= 0);

            // Credit limit hint.
            const limit = +partyMeta.credit_limit || 0;
            $form.find('#credit-warning').toggleClass('d-none', !(cfg.type === 'sale' && cfg.creditLimits && limit > 0 && due > 0 && due > limit))
                .text(`This bill alone exceeds the credit limit of ${BT.money(limit)}.`);
            return total;
        }

        // ── events ────────────────────────────────────────────────────────
        $tbody.on('input change', 'input, select', recalc);
        $tbody.on('change', '[data-f=unit_id]', function () {
            const $r = $(this).closest('tr');
            const $o = $(this).find('option:selected');
            const factor = +$o.data('factor') || 1;
            const fixed = isSale ? $o.data('sale') : $o.data('purchase');
            const base = isSale ? $r.data('base-sale') : $r.data('base-purchase');
            if (fixed !== '' && fixed !== undefined) $r.find('[data-f=price]').val(BT.round(+fixed, 4));
            else if (base !== '' && base !== undefined) $r.find('[data-f=price]').val(BT.round(+base * factor, 4));
            recalc();
        });
        $tbody.on('click', '[data-remove]', function () { $(this).closest('tr').remove(); recalc(); });
        $form.on('input change', '[name=discount],[name=other_charges],[name=round_off],[name=paid_amount]', recalc);
        $form.on('change', '[name=payment_mode]', function () {
            const cash = this.value === 'cash';
            $form.find('[name=paid_amount]').prop('readonly', cash);
            if (!cash) $form.find('[name=paid_amount]').val('0');
            recalc();
        });

        // Product search
        const $search = $form.find('#product-search');
        if ($search.length) {
            BT.productSelect($search, () => ({ warehouse_id: $warehouse.val(), party_id: $party.val() }));
            $search.on('select2:select', (e) => { addProduct(e.params.data, 1); $search.val(null).trigger('change'); });
        }

        // Barcode scanner: scanners type fast and end with Enter.
        const $scan = $form.find('#barcode');
        $scan.on('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            let code = $.trim(this.value);
            if (!code) return;
            let qty = 1;
            const m = code.match(/^(\d+(?:\.\d+)?)\*(.+)$/); // "5*8961234567890" → qty 5
            if (m) { qty = +m[1]; code = m[2]; }
            $.getJSON(BT.urls.products, { q: code, barcode: 1, warehouse_id: $warehouse.val(), party_id: $party.val() }, (d) => {
                if (d.results && d.results.length) { addProduct(d.results[0], qty); $scan.val('').trigger('focus'); }
                else { $scan.addClass('is-invalid'); setTimeout(() => $scan.removeClass('is-invalid'), 800); }
            });
        });

        // Party change → remember price level / credit limit
        $party.on('select2:select', (e) => { partyMeta = e.params.data; recalc(); });

        // Keyboard: F2 = scan box, F3 = search, F9 = save
        $(document).on('keydown', function (e) {
            if (e.key === 'F2') { e.preventDefault(); $scan.trigger('focus'); }
            if (e.key === 'F3') { e.preventDefault(); $search.select2('open'); }
            if (e.key === 'F9') { e.preventDefault(); $form.find('button[type=submit][value=print], button[type=submit]').first().trigger('click'); }
        });
        $form.on('keydown', 'input', function (e) {
            if (e.key === 'Enter' && this.id !== 'barcode' && this.type !== 'submit') e.preventDefault();
        });

        // Submit: drop zero lines (returns list every original line) and name fields.
        $form.on('submit', function (e) {
            let i = 0;
            $form.find('input[name^="items["],select[name^="items["]').removeAttr('name');
            $tbody.find('tr').each(function () {
                const $r = $(this);
                if (BT.num($r.find('[data-f=quantity]').val()) <= 0) { $r.find('[data-f]').removeAttr('name'); return; }
                $r.find('[data-f]').each(function () {
                    $(this).attr('name', `items[${i}][${$(this).data('f')}]`).prop('disabled', false);
                });
                i++;
            });
            if (i === 0) { e.preventDefault(); alert('Add at least one item.'); return false; }
        });

        // Initial rows
        (cfg.lines || []).forEach((l) => $tbody.append(rowHtml(l)));
        if ($form.find('[name=payment_mode]:checked').val() === 'cash') $form.find('[name=paid_amount]').prop('readonly', true);
        recalc();
        if (!isReturn) setTimeout(() => $scan.trigger('focus'), 100);
    };
})(jQuery);
