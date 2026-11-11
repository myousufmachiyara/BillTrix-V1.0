@php $w = (int) $b->setting('thermal_width', 80); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { size: {{ $w }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: "Courier New", monospace; font-size: {{ $w === 58 ? 10 : 12 }}px; width: {{ $w - 6 }}mm; margin: 0 auto; padding: 3mm 0; color: #000; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        h1 { font-size: 1.35em; margin: 0; }
        hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .item td { padding-top: 3px; }
        .big td { font-size: 1.3em; font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="c">
        <h1>{{ $b->name }}</h1>
        <div>{{ $invoice->branch->address ?: $b->address }}</div>
        <div>{{ $invoice->branch->phone ?: $b->phone }}</div>
        @if ($b->ntn)<div>NTN {{ $b->ntn }}@if ($b->strn) STRN {{ $b->strn }}@endif</div>@endif
    </div>
    <hr>
    <div class="c b">{{ strtoupper($invoice->title) }}</div>
    <table>
        <tr><td>No: {{ $invoice->number }}</td><td class="r">{{ $invoice->date->format('d-m-Y') }} {{ $invoice->created_at->format('H:i') }}</td></tr>
        <tr><td colspan="2">{{ $invoice->party->is_walk_in ? ($invoice->customer_name ?: 'Walk-in') : $invoice->party->name }} {{ $invoice->customer_phone }}</td></tr>
        <tr><td colspan="2">Cashier: {{ $invoice->creator?->name }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach ($invoice->items as $item)
            <tr class="item"><td colspan="3">{{ $item->product->name }}</td></tr>
            <tr>
                <td>{{ qty($item->quantity) }} {{ $item->unit->short_name }} x {{ money($item->price) }}</td>
                <td class="r">{{ (float) $item->discount_percent ? '-'.qty($item->discount_percent).'%' : '' }}</td>
                <td class="r">{{ money($item->amount) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Items</td><td class="r">{{ $invoice->items->count() }} / Qty {{ qty($invoice->items->sum('quantity')) }}</td></tr>
        @if ((float) $invoice->item_discount || (float) $invoice->discount)<tr><td>Discount</td><td class="r">-{{ money($invoice->item_discount + $invoice->discount) }}</td></tr>@endif
        @if ((float) $invoice->tax_total)<tr><td>Tax</td><td class="r">{{ money($invoice->tax_total) }}</td></tr>@endif
        @if ((float) $invoice->other_charges)<tr><td>Charges</td><td class="r">{{ money($invoice->other_charges) }}</td></tr>@endif
        @if ((float) $invoice->round_off)<tr><td>Round off</td><td class="r">{{ money($invoice->round_off) }}</td></tr>@endif
        <tr class="big"><td>TOTAL</td><td class="r">{{ money($invoice->total) }}</td></tr>
        <tr><td>{{ in_array($invoice->type, ['sale', 'purchase_return']) ? 'Received' : 'Paid' }}</td><td class="r">{{ money($invoice->paid_amount) }}</td></tr>
        @if ($invoice->balance > 0)<tr class="b"><td>Balance</td><td class="r">{{ money($invoice->balance) }}</td></tr>@endif
        @if ($previousBalance !== null)<tr><td>Prev. balance</td><td class="r">{{ drcr($previousBalance) }}</td></tr>@endif
    </table>
    <hr>
    <div class="c">{{ $b->setting('invoice_footer') }}</div>
    <div class="c" style="font-size:.85em;margin-top:4px">Powered by BillTrix</div>
    <p class="no-print c"><button onclick="window.print()">Print</button></p>
</body>
</html>
