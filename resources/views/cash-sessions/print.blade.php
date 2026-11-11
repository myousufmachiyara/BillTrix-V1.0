@php $w = (int) $b->setting('thermal_width', 80); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Z-report {{ $session->number }}</title>
    <style>
        @page { size: {{ $w }}mm auto; margin: 0; }
        body { font-family: "Courier New", monospace; font-size: {{ $w === 58 ? 10 : 12 }}px; width: {{ $w - 6 }}mm; margin: 0 auto; padding: 3mm 0; }
        .c { text-align: center; } .r { text-align: right; } hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; } .b { font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="c b">{{ $b->name }}</div><div class="c">{{ $session->branch->name }}</div>
    <hr><div class="c b">{{ $session->isOpen() ? 'X-REPORT (register open)' : 'Z-REPORT' }}</div>
    <table>
        <tr><td>Register</td><td class="r">{{ $session->number }}</td></tr>
        <tr><td>Cashier</td><td class="r">{{ $session->user->name }}</td></tr>
        <tr><td>Opened</td><td class="r">{{ $session->opened_at->format('d-m-Y H:i') }}</td></tr>
        <tr><td>Closed</td><td class="r">{{ $session->closed_at?->format('d-m-Y H:i') ?? '-' }}</td></tr>
    </table><hr>
    <table>
        <tr><td>Sales ({{ $summary['sales_count'] }})</td><td class="r">{{ money($summary['sales_total']) }}</td></tr>
        <tr><td>Returns ({{ $summary['returns_count'] }})</td><td class="r">-{{ money($summary['returns_total']) }}</td></tr>
        <tr class="b"><td>Net sales</td><td class="r">{{ money($summary['sales_total'] - $summary['returns_total']) }}</td></tr>
        <tr><td>Discounts</td><td class="r">{{ money($summary['discounts']) }}</td></tr>
        @if ($summary['tax'])<tr><td>Tax</td><td class="r">{{ money($summary['tax']) }}</td></tr>@endif
        @if ($summary['on_credit'] > 0)<tr><td>On credit</td><td class="r">{{ money($summary['on_credit']) }}</td></tr>@endif
    </table><hr>
    <table>@foreach ($summary['by_account'] as $a)<tr><td>{{ $a->name }}</td><td class="r">{{ money($a->received - $a->paid_out) }}</td></tr>@endforeach</table><hr>
    <table>
        <tr><td>Opening float</td><td class="r">{{ money($session->opening_float) }}</td></tr>
        <tr class="b"><td>Expected cash</td><td class="r">{{ money($session->expected_cash ?? $summary['expected_cash']) }}</td></tr>
        @unless ($session->isOpen())
            <tr><td>Counted cash</td><td class="r">{{ money($session->counted_cash) }}</td></tr>
            <tr class="b"><td>{{ $session->difference < 0 ? 'SHORT' : 'OVER' }}</td><td class="r">{{ money($session->difference) }}</td></tr>
        @endunless
    </table>
    @if ($session->denominations)<hr><table>@foreach ($session->denominations as $d => $n)<tr><td>{{ $d }} x {{ $n }}</td><td class="r">{{ money($d * $n) }}</td></tr>@endforeach</table>@endif
    <hr><p>Cashier ________________</p><p>Manager ________________</p>
    <p class="no-print c"><button onclick="window.print()">Print</button></p>
</body>
</html>
