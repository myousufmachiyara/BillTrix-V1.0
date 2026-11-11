<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $order->title }} {{ $order->number }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 12px; color: #222; margin: 0; }
        .wrap { max-width: 190mm; margin: 0 auto; padding: 8px; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #222; padding-bottom: 8px; }
        h1 { font-size: 20px; margin: 0 0 4px; } h2 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
        .muted { color: #666; } table { width: 100%; border-collapse: collapse; }
        .items th { background: #f0f0f0; border: 1px solid #bbb; padding: 5px; text-align: left; } .items td { border: 1px solid #ccc; padding: 4px 5px; }
        .r { text-align: right; white-space: nowrap; } .totals { width: 45%; margin-left: auto; margin-top: 8px; } .totals td { padding: 3px 5px; }
        .grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #222; }
        .terms { margin-top: 14px; white-space: pre-line; font-size: 11px; }
        .foot { display: flex; justify-content: space-between; margin-top: 50px; } .sign { border-top: 1px solid #444; width: 30%; text-align: center; padding-top: 4px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="wrap">
    <div class="head">
        <div>
            @if ($b->logo_path)<img src="{{ asset('storage/'.$b->logo_path) }}" alt="" style="max-height:60px"><br>@endif
            <h1>{{ $b->legal_name ?: $b->name }}</h1>
            <div>{{ $order->branch->address ?: $b->address }}</div>
            <div>{{ $order->branch->phone ?: $b->phone }} {{ $b->email ? '· '.$b->email : '' }}</div>
            @if ($b->ntn)<div>NTN: {{ $b->ntn }} @if ($b->strn) · STRN: {{ $b->strn }} @endif</div>@endif
        </div>
        <div style="text-align:right">
            <h2>{{ $order->title }}</h2>
            <div><strong>{{ $order->number }}</strong></div>
            <div>Date: {{ $order->date->format('d M Y') }}</div>
            @if ($order->due_date)<div>{{ $order->type === 'quotation' ? 'Valid until' : 'Required by' }}: {{ $order->due_date->format('d M Y') }}</div>@endif
            @if ($order->reference)<div>Ref: {{ $order->reference }}</div>@endif
        </div>
    </div>
    <div style="margin:12px 0">
        <div class="muted">{{ $order->type === 'purchase_order' ? 'To supplier' : 'To' }}</div>
        <strong>{{ $order->party->name }}</strong>
        <div>{{ $order->party->address }} {{ $order->party->city }}</div><div>{{ $order->party->phone }}</div>
    </div>
    <table class="items">
        <thead><tr><th>#</th><th>Description</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Disc %</th><th class="r">Amount</th></tr></thead>
        <tbody>
        @foreach ($order->items as $i)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $i->product->name }}</td><td class="r">{{ qty($i->quantity) }} {{ $i->unit->short_name }}</td>
                <td class="r">{{ money($i->price) }}</td><td class="r">{{ (float) $i->discount_percent ? qty($i->discount_percent) : '' }}</td><td class="r">{{ money($i->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Gross</td><td class="r">{{ money($order->subtotal) }}</td></tr>
        @if ((float) $order->item_discount)<tr><td>Item discounts</td><td class="r">-{{ money($order->item_discount) }}</td></tr>@endif
        @if ((float) $order->tax_total)<tr><td>Sales tax</td><td class="r">{{ money($order->tax_total) }}</td></tr>@endif
        @if ((float) $order->discount)<tr><td>Discount</td><td class="r">-{{ money($order->discount) }}</td></tr>@endif
        @if ((float) $order->other_charges)<tr><td>Other charges</td><td class="r">{{ money($order->other_charges) }}</td></tr>@endif
        <tr class="grand"><td>Total</td><td class="r">{{ $b->currency }} {{ money($order->total) }}</td></tr>
    </table>
    <div style="margin-top:8px;font-style:italic">{{ in_words($order->total) }}</div>
    @if ($order->notes)<p><strong>Notes:</strong> {{ $order->notes }}</p>@endif
    @if ($order->terms)<div class="terms"><strong>Terms & conditions</strong><br>{{ $order->terms }}</div>@endif
    <div class="foot"><div class="sign">Prepared by {{ $order->creator?->name }}</div><div class="sign">Authorised signature</div></div>
    <p class="no-print" style="text-align:center;margin-top:20px"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></p>
</div>
</body>
</html>
