<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Barcode Labels') }}</title>
    <style>
        @page { size: {{ $sheet ? 'A4' : $w.'mm '.$h.'mm' }}; margin: {{ $sheet ? '8mm 5mm' : '0' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
        .sheet { display: flex; flex-wrap: wrap; {{ $sheet ? 'gap: 0;' : '' }} }
        .label { width: {{ $w }}mm; height: {{ $h }}mm; padding: 1.5mm 2mm; overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
                 {{ $sheet ? 'border: 0.2mm dashed #ddd;' : 'page-break-after: always;' }} }
        .label .nm { font-size: {{ $h < 30 ? '7.5pt' : '8.5pt' }}; font-weight: bold; line-height: 1.1; max-height: 2.3em; overflow: hidden; width: 100%; }
        .label .var { font-size: 7.5pt; line-height: 1.1; }
        .label svg { width: 100%; height: {{ $h < 30 ? '9mm' : '12mm' }}; margin: 0.8mm 0 0.3mm; }
        .label .code { font-size: 7pt; letter-spacing: .5px; }
        .label .pr { font-size: {{ $h < 30 ? '9pt' : '10.5pt' }}; font-weight: bold; }
        .toolbar { padding: 10px; background: #f3f4f6; font-size: 14px; }
        @media print { .toolbar { display: none; } .label { border: 0 !important; } }
    </style>
</head>
<body onload="window.print()">
    <div class="toolbar">{{ count($labels) }} {{ __('labels') }} · <button onclick="window.print()">{{ __('Print') }}</button></div>
    <div class="sheet">
        @foreach ($labels as $l)
            <div class="label">
                @if ($showName)<div class="nm">{{ $l['name'] }}</div>@if ($l['variant'])<div class="var">{{ $l['variant'] }}</div>@endif @endif
                {!! \App\Support\Code128::svg($l['code']) !!}
                <div class="code">{{ $l['code'] }}</div>
                @if ($showPrice)<div class="pr">{{ $business->currency ?? 'Rs' }} {{ money($l['price']) }}</div>@endif
            </div>
        @endforeach
    </div>
</body>
</html>
