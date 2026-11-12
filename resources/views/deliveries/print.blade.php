<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Delivery Challan') }} {{ $delivery->number }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 12px; color: #222; margin: 0; }
        .wrap { max-width: 190mm; margin: 0 auto; padding: 8px; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #222; padding-bottom: 8px; }
        h1 { font-size: 20px; margin: 0 0 4px; } h2 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f0f0f0; border: 1px solid #bbb; padding: 6px; text-align: left; } td { border: 1px solid #ccc; padding: 6px; }
        .r { text-align: right; } .meta { display: flex; justify-content: space-between; margin-top: 12px; }
        .foot { display: flex; justify-content: space-between; margin-top: 60px; } .sign { border-top: 1px solid #444; width: 28%; text-align: center; padding-top: 4px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="wrap">
    <div class="head">
        <div><h1>{{ $b->legal_name ?: $b->name }}</h1><div>{{ $delivery->branch->address ?: $b->address }}</div><div>{{ $delivery->branch->phone ?: $b->phone }}</div></div>
        <div style="text-align:right"><h2>{{ __('Delivery Challan') }}</h2><div><strong>{{ $delivery->number }}</strong></div><div>{{ __('Date:') }} {{ $delivery->date->format('d M Y') }}</div>
            @if ($delivery->order)<div>{{ __('Order:') }} {{ $delivery->order->number }}</div>@endif</div>
    </div>
    <div class="meta">
        <div><div style="color:#666">{{ __('Deliver to') }}</div><strong>{{ $delivery->party->name }}</strong><div>{{ $delivery->ship_to ?: $delivery->party->address }}</div><div>{{ $delivery->party->phone }}</div></div>
        <div style="text-align:right">@if ($delivery->vehicle_no)<div>{{ __('Vehicle:') }} {{ $delivery->vehicle_no }}</div>@endif @if ($delivery->driver)<div>{{ __('Driver:') }} {{ $delivery->driver }}</div>@endif</div>
    </div>
    <table>
        <thead><tr><th style="width:30px">#</th><th>{{ __('Description') }}</th><th class="r" style="width:140px">{{ __('Quantity') }}</th><th style="width:160px">{{ __('Remarks') }}</th></tr></thead>
        <tbody>
        @foreach ($delivery->items as $i)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $i->product->name }}</td><td class="r">{{ qty($i->quantity) }} {{ $i->unit->short_name }}</td><td></td></tr>
        @endforeach
        </tbody>
    </table>
    @if ($delivery->notes)<p><strong>{{ __('Notes:') }}</strong> {{ $delivery->notes }}</p>@endif
    <p style="margin-top:14px">{{ __('Received the above goods in good condition.') }}</p>
    <div class="foot"><div class="sign">{{ __('Dispatched by') }}</div><div class="sign">{{ __('Driver') }}</div><div class="sign">{{ __('Received by (name, stamp)') }}</div></div>
    <p class="no-print" style="text-align:center;margin-top:20px"><button onclick="window.print()">{{ __('Print') }}</button> <button onclick="window.close()">{{ __('Close') }}</button></p>
</div>
</body>
</html>
