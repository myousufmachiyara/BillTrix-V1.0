<x-card :body="false">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Voucher</th><th>Description</th>@isset($showAccount)<th>Account</th>@endisset<th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
            <tbody>
                <tr class="fw-semibold"><td colspan="{{ isset($showAccount) ? 6 : 5 }}">Opening balance</td><td class="num">{{ drcr($data['opening']) }}</td></tr>
                @foreach ($data['lines'] as $l)
                    <tr>
                        <td class="text-nowrap">{{ $l->date->format('d-m-Y') }}</td>
                        <td class="text-nowrap">{{ $l->entry->number }}</td>
                        <td>{{ $l->memo ?: $l->entry->narration }} @if ($l->entry->reference && $l->entry->reference !== $l->entry->number)<span class="text-muted small">· {{ $l->entry->reference }}</span>@endif</td>
                        @isset($showAccount)<td class="small text-muted">{{ $l->account->name }}</td>@endisset
                        <td class="num">{{ (float) $l->debit ? money($l->debit) : '' }}</td>
                        <td class="num">{{ (float) $l->credit ? money($l->credit) : '' }}</td>
                        <td class="num">{{ drcr($l->running) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="{{ isset($showAccount) ? 4 : 3 }}">Totals / closing balance</td><td class="num">{{ money($data['debit']) }}</td><td class="num">{{ money($data['credit']) }}</td><td class="num">{{ drcr($data['closing']) }}</td></tr></tfoot>
        </table>
    </div>
</x-card>
