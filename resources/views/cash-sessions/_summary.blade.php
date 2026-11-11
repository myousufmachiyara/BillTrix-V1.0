<table class="table table-sm mb-0">
    <tr><td>Sales</td><td class="num">{{ $summary['sales_count'] }} bills</td><td class="num fw-semibold">{{ money($summary['sales_total']) }}</td></tr>
    <tr><td>Returns</td><td class="num">{{ $summary['returns_count'] }}</td><td class="num text-danger">{{ $summary['returns_total'] > 0 ? '-'.money($summary['returns_total']) : '0.00' }}</td></tr>
    <tr><td>Discounts given</td><td></td><td class="num">{{ money($summary['discounts']) }}</td></tr>
    @if ($summary['tax'])<tr><td>Sales tax</td><td></td><td class="num">{{ money($summary['tax']) }}</td></tr>@endif
    @if ($summary['on_credit'] > 0)<tr><td>Sold on credit</td><td></td><td class="num">{{ money($summary['on_credit']) }}</td></tr>@endif
    <tr class="table-light"><th colspan="3">Collections by account</th></tr>
    @forelse ($summary['by_account'] as $a)
        <tr><td>{{ $a->name }}</td><td class="num small text-muted">@if ((float) $a->paid_out) in {{ money($a->received) }} / out {{ money($a->paid_out) }} @endif</td><td class="num">{{ money($a->received - $a->paid_out) }}</td></tr>
    @empty
        <tr><td colspan="3" class="text-muted">No payments yet.</td></tr>
    @endforelse
    <tr class="table-light"><th colspan="3">Cash drawer — {{ $session->account->name }}</th></tr>
    <tr><td>Opening float</td><td></td><td class="num">{{ money($session->opening_float) }}</td></tr>
    <tr><td>Net cash from bills</td><td></td><td class="num">{{ money($summary['cash_net']) }}</td></tr>
    <tr class="fw-bold"><td>Expected in drawer</td><td></td><td class="num">{{ money($session->isOpen() ? $summary['expected_cash'] : $session->expected_cash) }}</td></tr>
</table>
