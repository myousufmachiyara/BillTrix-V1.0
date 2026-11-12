<table class="table table-sm mb-0">
    <tr><td>{{ __('Sales') }}</td><td class="num">{{ $summary['sales_count'] }} {{ __('bills') }}</td><td class="num fw-semibold">{{ money($summary['sales_total']) }}</td></tr>
    <tr><td>{{ __('Returns') }}</td><td class="num">{{ $summary['returns_count'] }}</td><td class="num text-danger">{{ $summary['returns_total'] > 0 ? '-'.money($summary['returns_total']) : '0.00' }}</td></tr>
    <tr><td>{{ __('Discounts given') }}</td><td></td><td class="num">{{ money($summary['discounts']) }}</td></tr>
    @if ($summary['tax'])<tr><td>{{ __('Sales tax') }}</td><td></td><td class="num">{{ money($summary['tax']) }}</td></tr>@endif
    @if ($summary['on_credit'] > 0)<tr><td>{{ __('Sold on credit') }}</td><td></td><td class="num">{{ money($summary['on_credit']) }}</td></tr>@endif
    <tr class="table-light"><th colspan="3">{{ __('Collections by account') }}</th></tr>
    @forelse ($summary['by_account'] as $a)
        <tr><td>{{ $a->name }}</td><td class="num small text-muted">@if ((float) $a->paid_out) {{ __('in') }} {{ money($a->received) }} {{ __('/ out') }} {{ money($a->paid_out) }} @endif</td><td class="num">{{ money($a->received - $a->paid_out) }}</td></tr>
    @empty
        <tr><td colspan="3" class="text-muted">{{ __('No payments yet.') }}</td></tr>
    @endforelse
    <tr class="table-light"><th colspan="3">{{ __('Cash drawer —') }} {{ $session->account->name }}</th></tr>
    <tr><td>{{ __('Opening float') }}</td><td></td><td class="num">{{ money($session->opening_float) }}</td></tr>
    <tr><td>{{ __('Net cash from bills') }}</td><td></td><td class="num">{{ money($summary['cash_net']) }}</td></tr>
    <tr class="fw-bold"><td>{{ __('Expected in drawer') }}</td><td></td><td class="num">{{ money($session->isOpen() ? $summary['expected_cash'] : $session->expected_cash) }}</td></tr>
</table>
