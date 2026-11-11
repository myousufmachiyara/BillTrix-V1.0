<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->title }} {{ $invoice->number }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 12px; color: #222; margin: 0; }
        .wrap { max-width: 190mm; margin: 0 auto; padding: 8px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 8px; }
        .head h1 { font-size: 20px; margin: 0 0 4px; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
        .meta { display: flex; justify-content: space-between; margin: 12px 0; }
        .meta .box { width: 48%; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; }
        .items th { background: #f0f0f0; border: 1px solid #bbb; padding: 5px; text-align: left; }
        .items td { border: 1px solid #ccc; padding: 4px 5px; }
        .r { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: auto; margin-top: 8px; }
        .totals td { padding: 3px 5px; }
        .totals .grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #222; }
        .words { margin-top: 8px; font-style: italic; }
        .foot { display: flex; justify-content: space-between; margin-top: 50px; }
        .sign { border-top: 1px solid #444; width: 30%; text-align: center; padding-top: 4px; }
        .note { margin-top: 18px; text-align: center; color: #555; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="wrap">
    <div class="head">
        <div>
            @if ($b->logo_path)<img src="{{ asset('storage/'.$b->logo_path) }}" alt="" style="max-height:60px"><br>@endif
            <h1>{{ $b->legal_name ?: $b->name }}</h1>
            <div>{{ $invoice->branch->address ?: $b->address }}</div>
            <div>{{ $invoice->branch->phone ?: $b->phone }} {{ $b->email ? '· '.$b->email : '' }}</div>
            @if ($b->ntn || $b->strn)<div>NTN: {{ $b->ntn }} @if ($b->strn) · STRN: {{ $b->strn }} @endif</div>@endif
        </div>
        <div class="doc-title">
            <h2>{{ $invoice->title }}</h2>
            <div><strong>{{ $invoice->number }}</strong></div>
            <div>Date: {{ $invoice->date->format('d M Y') }}</div>
            @if ($invoice->due_date)<div>Due: {{ $invoice->due_date->format('d M Y') }}</div>@endif
            <div>{{ ucfirst($invoice->payment_mode) }}</div>
        </div>
    </div>

    <div class="meta">
        <div class="box">
            <div class="muted">{{ $invoice->isSaleSide() ? 'Bill to' : 'Supplier' }}</div>
            <strong>{{ $invoice->party->is_walk_in ? ($invoice->customer_name ?: 'Walk-in Customer') : $invoice->party->name }}</strong>
            @unless ($invoice->party->is_walk_in)
                <div>{{ $invoice->party->address }} {{ $invoice->party->city }}</div>
                <div>{{ $invoice->party->phone }}</div>
                @if ($invoice->party->ntn)<div>NTN: {{ $invoice->party->ntn }}</div>@endif
            @else
                <div>{{ $invoice->customer_phone }}</div>
            @endunless
        </div>
        <div class="box" style="text-align:right">
            @if ($invoice->reference)<div><span class="muted">Ref:</span> {{ $invoice->reference }}</div>@endif
            @if ($invoice->returnOf)<div><span class="muted">Against:</span> {{ $invoice->returnOf->number }}</div>@endif
        </div>
    </div>

    @php $showTax = (float) $invoice->tax_total > 0; $showDisc = (float) $invoice->item_discount > 0; @endphp
    <table class="items">
        <thead><tr><th style="width:30px">#</th><th>Description</th><th class="r">Qty</th><th class="r">Rate</th>@if ($showDisc)<th class="r">Disc %</th>@endif @if ($showTax)<th class="r">Tax</th>@endif<th class="r">Amount</th></tr></thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="r">{{ qty($item->quantity) }} {{ $item->unit->short_name }}</td>
                <td class="r">{{ money($item->price) }}</td>
                @if ($showDisc)<td class="r">{{ (float) $item->discount_percent ? qty($item->discount_percent) : '' }}</td>@endif
                @if ($showTax)<td class="r">{{ money($item->tax_amount) }}</td>@endif
                <td class="r">{{ money($item->amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Gross</td><td class="r">{{ money($invoice->subtotal) }}</td></tr>
        @if ($showDisc)<tr><td>Item discounts</td><td class="r">-{{ money($invoice->item_discount) }}</td></tr>@endif
        @if ($showTax)<tr><td>Sales tax</td><td class="r">{{ money($invoice->tax_total) }}</td></tr>@endif
        @if ((float) $invoice->discount)<tr><td>Discount</td><td class="r">-{{ money($invoice->discount) }}</td></tr>@endif
        @if ((float) $invoice->other_charges)<tr><td>Other charges</td><td class="r">{{ money($invoice->other_charges) }}</td></tr>@endif
        @if ((float) $invoice->round_off)<tr><td>Round off</td><td class="r">{{ money($invoice->round_off) }}</td></tr>@endif
        <tr class="grand"><td>Total</td><td class="r">{{ $b->currency }} {{ money($invoice->total) }}</td></tr>
        <tr><td>{{ in_array($invoice->type, ['sale', 'purchase_return']) ? 'Received' : 'Paid' }}</td><td class="r">{{ money($invoice->paid_amount) }}</td></tr>
        <tr><td><strong>Balance</strong></td><td class="r"><strong>{{ money($invoice->balance) }}</strong></td></tr>
        @if ($previousBalance !== null)
            <tr><td class="muted">Previous balance</td><td class="r muted">{{ drcr($previousBalance) }}</td></tr>
            <tr><td class="muted">Account balance now</td><td class="r muted">{{ drcr($currentBalance) }}</td></tr>
        @endif
    </table>
    <div class="words">{{ in_words($invoice->total) }}</div>
    @if ($invoice->notes)<p><strong>Notes:</strong> {{ $invoice->notes }}</p>@endif

    <div class="foot">
        <div class="sign">Prepared by {{ $invoice->creator?->name }}</div>
        <div class="sign">Received by</div>
    </div>
    <div class="note">{{ $b->setting('invoice_footer') }}</div>
    <p class="no-print" style="text-align:center;margin-top:20px"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></p>
</div>
</body>
</html>
