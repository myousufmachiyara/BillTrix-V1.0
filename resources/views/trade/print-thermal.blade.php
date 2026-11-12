@php $w = (int) $b->setting('thermal_width', 80); @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { size: {{ $w }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: "Courier New", monospace; font-size: {{ $w === 58 ? 10 : 12 }}px; width: {{ $w - 6 }}mm; margin: 0 auto; padding: 3mm 0; color: #000; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        [dir=rtl] body { font-family: "Noto Nastaliq Urdu", "Jameel Noori Nastaleeq", serif; line-height: 1.9; }
        [dir=rtl] .r { text-align: left; } [dir=rtl] td { direction: rtl; } .num-ltr { direction: ltr; unicode-bidi: embed; }
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
        @if ($b->ntn)<div>{{ __('NTN') }} {{ $b->ntn }}@if ($b->strn) {{ __('STRN') }} {{ $b->strn }}@endif</div>@endif
    </div>
    <hr>
    <div class="c b">{{ app()->getLocale() === 'en' ? strtoupper($invoice->title) : __($invoice->title) }}</div>
    <table>
        <tr><td>{{ __('No:') }} {{ $invoice->number }}</td><td class="r">{{ $invoice->date->format('d-m-Y') }} {{ $invoice->created_at->format('H:i') }}</td></tr>
        <tr><td colspan="2">{{ $invoice->party->is_walk_in ? ($invoice->customer_name ?: __('Walk-in')) : $invoice->party->name }} {{ $invoice->customer_phone }}</td></tr>
        <tr><td colspan="2">{{ __('Cashier:') }} {{ $invoice->creator?->name }}</td></tr>
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
        <tr><td>{{ __('Items') }}</td><td class="r">{{ $invoice->items->count() }} {{ __('/ Qty') }} {{ qty($invoice->items->sum('quantity')) }}</td></tr>
        @if ((float) $invoice->item_discount || (float) $invoice->discount)<tr><td>{{ __('Discount') }}</td><td class="r">-{{ money($invoice->item_discount + $invoice->discount) }}</td></tr>@endif
        @if ((float) $invoice->tax_total)<tr><td>{{ __('Tax') }}</td><td class="r">{{ money($invoice->tax_total) }}</td></tr>@endif
        @if ((float) $invoice->other_charges)<tr><td>{{ __('Charges') }}</td><td class="r">{{ money($invoice->other_charges) }}</td></tr>@endif
        @if ((float) $invoice->round_off)<tr><td>{{ __('Round off') }}</td><td class="r">{{ money($invoice->round_off) }}</td></tr>@endif
        @if ((float) $invoice->wht_amount)<tr><td>{{ __('WHT') }} {{ qty($invoice->wht_rate) }}%</td><td class="r">{{ money($invoice->wht_amount) }}</td></tr>@endif
        @if ((float) $invoice->service_fee)<tr><td>{{ __('FBR POS fee') }}</td><td class="r">{{ money($invoice->service_fee) }}</td></tr>@endif
        <tr class="big"><td>{{ __('TOTAL') }}</td><td class="r">{{ money($invoice->total) }}</td></tr>
        <tr><td>{{ __(in_array($invoice->type, ['sale', 'purchase_return']) ? 'Received' : 'Paid') }}</td><td class="r">{{ money($invoice->paid_amount) }}</td></tr>
        @if ($invoice->balance > 0)<tr class="b"><td>{{ __('Balance') }}</td><td class="r">{{ money($invoice->balance) }}</td></tr>@endif
        @if ($previousBalance !== null)<tr><td>{{ __('Prev. balance') }}</td><td class="r">{{ drcr($previousBalance) }}</td></tr>@endif
    </table>
    @include('trade._fbr', ['qrSize' => 90])
    <hr>
    <div class="c">{{ $b->setting('invoice_footer') }}</div>
    <div class="c" style="font-size:.85em;margin-top:4px">{{ __('Powered by BillTrix') }}</div>
    <p class="no-print c"><button onclick="window.print()">{{ __('Print') }}</button></p>
</body>
</html>
