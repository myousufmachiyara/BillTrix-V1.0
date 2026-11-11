@php
    $titles = ['receipt' => 'Receipt Voucher', 'payment' => 'Payment / Expense Voucher', 'journal' => 'Journal Voucher', 'contra' => 'Cash ⇄ Bank Transfer'];
    $editing = $entry->exists;
    $prefParty = $editing ? $entry->party : (request('party_id') ? \App\Models\Party::find(request('party_id')) : null);
    $counterIsParty = $editing ? (bool) $counterLine?->party_id : true;
    $counterIsParty = old('counter_account_id') ? false : $counterIsParty;
@endphp
@extends('layouts.app')
@section('title', ($editing ? 'Edit ' : 'New ').$titles[$type].($editing ? ' '.$entry->number : ''))
@section('content')
<form method="POST" action="{{ $editing ? route('vouchers.update', [$type, $entry]) : route('vouchers.store', $type) }}" id="voucher-form" autocomplete="off">
    @csrf @if ($editing) @method('PUT') @endif
    <div class="row">
        <div class="col-lg-{{ $type === 'journal' ? '12' : '8' }}">
            <x-card>
                <div class="row">
                    <x-input name="date" label="Date" type="date" :value="$entry->date?->toDateString()" required col="col-md-3" />
                    @if ($branches->count() > 1)
                        <x-select name="branch_id" label="Branch" :options="$branches" :value="$entry->branch_id ?? auth()->user()->branch_id" col="col-md-3" />
                    @endif
                    <x-input name="reference" label="Reference / cheque no." :value="$entry->reference" col="col-md-3" />
                </div>

                @if ($type === 'receipt' || $type === 'payment')
                    <div class="row">
                        <x-select name="cash_account_id" :label="$type === 'receipt' ? 'Received into' : 'Paid from'" :value="$cashLine?->account_id" required col="col-md-4">
                            @foreach ($cashAccounts as $a)<option value="{{ $a->id }}" @selected((int) old('cash_account_id', $cashLine?->account_id) === $a->id)>{{ $a->name }}</option>@endforeach
                        </x-select>
                        <div class="col-md-5 mb-3">
                            <div class="btn-group btn-group-sm mb-1" role="group">
                                <input type="radio" class="btn-check" name="_counter" id="c-party" value="party" @checked($counterIsParty)>
                                <label class="btn btn-outline-secondary" for="c-party">{{ $type === 'receipt' ? 'From customer / supplier' : 'To supplier / customer' }}</label>
                                <input type="radio" class="btn-check" name="_counter" id="c-account" value="account" @checked(! $counterIsParty)>
                                <label class="btn btn-outline-secondary" for="c-account">{{ $type === 'receipt' ? 'Other income / account' : 'Expense / account' }}</label>
                            </div>
                            <div id="counter-party" @class(['d-none' => ! $counterIsParty])>
                                <x-party-select :party="$prefParty" />
                            </div>
                            <div id="counter-account" @class(['d-none' => $counterIsParty])>
                                <select name="counter_account_id" class="form-select select2">
                                    <option value="">Choose account…</option>
                                    @foreach ($accounts as $a)
                                        @continue($a->isControlAccount())
                                        <option value="{{ $a->id }}" @selected((int) old('counter_account_id', $counterLine?->party_id ? null : $counterLine?->account_id) === $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <x-input name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="$editing ? (float) $entry->amount : null" required col="col-md-3" class="form-control form-control-lg text-end" />
                        <x-input name="narration" label="Narration" :value="$entry->narration" col="col-12" />
                    </div>
                @elseif ($type === 'contra')
                    @php $from = $editing ? $entry->lines->firstWhere('credit', '>', 0)?->account_id : null; $to = $editing ? $entry->lines->firstWhere('debit', '>', 0)?->account_id : null; @endphp
                    <div class="row">
                        <x-select name="from_account_id" label="From" :options="$cashAccounts->pluck('name', 'id')" :value="$from" required col="col-md-4" />
                        <x-select name="to_account_id" label="To" :options="$cashAccounts->pluck('name', 'id')" :value="$to" required col="col-md-4" />
                        <x-input name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="$editing ? (float) $entry->amount : null" required col="col-md-4" />
                        <x-input name="narration" label="Narration" :value="$entry->narration" col="col-12" placeholder="e.g. Cash deposited in bank" />
                    </div>
                @else
                    <table class="table table-sm" id="jv-lines">
                        <thead class="table-light"><tr><th style="width:35%">Account</th><th style="width:22%">Customer / supplier</th><th>Memo</th><th class="num" style="width:13%">Debit</th><th class="num" style="width:13%">Credit</th><th></th></tr></thead>
                        <tbody></tbody>
                        <tfoot><tr><td colspan="3"><button type="button" class="btn btn-sm btn-outline-primary" id="jv-add"><i class="fas fa-plus"></i> Line</button> <span id="jv-diff" class="ms-3 fw-semibold"></span></td><td class="num" id="jv-dr">0.00</td><td class="num" id="jv-cr">0.00</td><td></td></tr></tfoot>
                    </table>
                    <x-input name="narration" label="Narration" :value="$entry->narration" col="col-12 px-0" />
                @endif
                <div class="text-end">
                    <a href="{{ route('vouchers.index', $type) }}" class="btn btn-default">Cancel</a>
                    @unless ($editing)<button type="submit" name="save_new" value="1" class="btn btn-outline-primary">Save & New</button>@endunless
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </x-card>
        </div>

        @if ($type === 'receipt' || $type === 'payment')
            <div class="col-lg-4">
                <x-card title="Apply to invoices">
                    <div id="alloc-empty" class="text-muted small">Choose a customer/supplier to see open invoices. Anything not applied stays on account.</div>
                    <div id="alloc-balance" class="mb-2 small"></div>
                    <table class="table table-sm d-none" id="alloc-table">
                        <thead><tr><th>Invoice</th><th class="num">Due</th><th class="num" style="width:110px">Apply</th></tr></thead>
                        <tbody></tbody>
                        <tfoot><tr><td colspan="2"><a href="#" id="alloc-auto" class="small">Apply oldest first</a></td><td class="num" id="alloc-total">0.00</td></tr></tfoot>
                    </table>
                </x-card>
            </div>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    const type = @js($type);

    if (type === 'receipt' || type === 'payment') {
        const $party = $('[name=party_id]');
        const existing = @js($editing ? $entry->allocations->pluck('amount', 'invoice_id')->map(fn ($v) => (float) $v) : (object) []);
        const preselect = @js((int) request('invoice_id'));

        $('[name=_counter]').on('change', function () {
            const party = this.value === 'party';
            $('#counter-party').toggleClass('d-none', !party);
            $('#counter-account').toggleClass('d-none', party);
            if (party) $('[name=counter_account_id]').val('').trigger('change'); else { $party.val(null).trigger('change'); loadInvoices(null); }
        });

        function loadInvoices(id) {
            const $tb = $('#alloc-table tbody').empty();
            $('#alloc-table').addClass('d-none'); $('#alloc-empty').removeClass('d-none'); $('#alloc-balance').text('');
            if (!id) return;
            const url = @js(route('parties.open-invoices', '__ID__')).replace('__ID__', id);
            $.getJSON(url, { for: type }, function (d) {
                $('#alloc-balance').html('Balance: <strong>' + BT.money(Math.abs(d.balance)) + (d.balance > 0 ? ' Dr' : d.balance < 0 ? ' Cr' : '') + '</strong>');
                const rows = d.invoices.map(i => ({ ...i, balance: i.balance + (existing[i.id] || 0) }));
                Object.keys(existing).forEach(k => { if (!rows.find(r => +r.id === +k)) rows.push({ id: +k, number: '#' + k, date: '', balance: existing[k] }); });
                if (!rows.length) return;
                rows.forEach((i, n) => {
                    const val = existing[i.id] || (preselect === +i.id ? i.balance : '');
                    $tb.append(`<tr><td>${BT.esc(i.number)}<div class="small text-muted">${i.date}</div></td><td class="num">${BT.money(i.balance)}</td>
                        <td><input type="hidden" name="allocations[${n}][invoice_id]" value="${i.id}">
                        <input type="number" step="0.01" min="0" max="${i.balance}" name="allocations[${n}][amount]" class="form-control form-control-sm text-end alloc" data-max="${i.balance}" value="${val}"></td></tr>`);
                });
                $('#alloc-table').removeClass('d-none'); $('#alloc-empty').addClass('d-none');
                if (preselect && !$('[name=amount]').val()) $('[name=amount]').val(rows.find(r => +r.id === preselect)?.balance || '');
                sum();
            });
        }
        function sum() {
            let t = 0; $('.alloc').each(function () { t += BT.num(this.value); });
            $('#alloc-total').text(BT.money(t)).toggleClass('text-danger', t > BT.num($('[name=amount]').val()) + 0.001);
        }
        $(document).on('input', '.alloc,[name=amount]', sum);
        $('#alloc-auto').on('click', function (e) {
            e.preventDefault();
            let left = BT.num($('[name=amount]').val());
            $('.alloc').each(function () { const v = Math.min(left, +$(this).data('max')); this.value = v > 0 ? v.toFixed(2) : ''; left = BT.round(left - Math.max(v, 0)); });
            sum();
        });
        $party.on('change', function () { loadInvoices(this.value); });
        if ($party.val()) loadInvoices($party.val());
    }

    if (type === 'journal') {
        const accounts = @js($accounts->map(fn ($a) => ['id' => $a->id, 'text' => $a->code.' — '.$a->name, 'control' => $a->isControlAccount()])->values());
        const initial = @js($editing ? $entry->lines->map(fn ($l) => ['account_id' => $l->account_id, 'party_id' => $l->party_id, 'party' => $l->party?->name, 'memo' => $l->memo, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->values() : array_values(old('lines', [])));
        let n = 0;
        function addLine(l = {}) {
            const i = n++;
            const opts = accounts.map(a => `<option value="${a.id}" data-control="${a.control ? 1 : 0}" ${+a.id === +l.account_id ? 'selected' : ''}>${BT.esc(a.text)}</option>`).join('');
            const $tr = $(`<tr>
                <td><select name="lines[${i}][account_id]" class="form-select form-select-sm jv-acc" required><option value=""></option>${opts}</select></td>
                <td><select name="lines[${i}][party_id]" class="form-select form-select-sm jv-party">${l.party_id ? `<option value="${l.party_id}" selected>${BT.esc(l.party || '')}</option>` : '<option value=""></option>'}</select></td>
                <td><input name="lines[${i}][memo]" class="form-control form-control-sm" value="${BT.esc(l.memo || '')}"></td>
                <td><input type="number" step="0.01" min="0" name="lines[${i}][debit]" class="form-control form-control-sm text-end jv-dr" value="${l.debit || ''}"></td>
                <td><input type="number" step="0.01" min="0" name="lines[${i}][credit]" class="form-control form-control-sm text-end jv-cr" value="${l.credit || ''}"></td>
                <td><button type="button" class="btn btn-link text-danger p-0 jv-del"><i class="fas fa-times"></i></button></td></tr>`);
            $('#jv-lines tbody').append($tr);
            $tr.find('.jv-acc').select2({ theme: 'bootstrap', placeholder: 'Account…' });
            BT.partySelect($tr.find('.jv-party'));
        }
        function totals() {
            let dr = 0, cr = 0;
            $('.jv-dr').each(function () { dr += BT.num(this.value); });
            $('.jv-cr').each(function () { cr += BT.num(this.value); });
            $('#jv-dr').text(BT.money(dr)); $('#jv-cr').text(BT.money(cr));
            const diff = BT.round(dr - cr);
            $('#jv-diff').text(diff ? 'Difference: ' + BT.money(Math.abs(diff)) : (dr ? '✓ Balanced' : '')).toggleClass('text-danger', !!diff).toggleClass('text-success', !diff);
        }
        $('#jv-add').on('click', () => addLine());
        $(document).on('click', '.jv-del', function () { $(this).closest('tr').remove(); totals(); });
        $(document).on('input', '.jv-dr,.jv-cr', function () {
            if (BT.num(this.value)) $(this).closest('tr').find(this.classList.contains('jv-dr') ? '.jv-cr' : '.jv-dr').val('');
            totals();
        });
        (initial.length ? initial : [{}, {}]).forEach(addLine);
        totals();
    }
})(jQuery);
</script>
@endpush
