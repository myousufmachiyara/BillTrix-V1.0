{{-- FBR fiscal block for printed invoices: number, QR (generated in the browser) and the FBR POS logo if installed. --}}
@if ($invoice->fbr_status)
    <div class="fbr-block" style="text-align:center;margin-top:8px">
        @if ($invoice->fbr_status === 'synced')
            @if (file_exists(public_path('assets/img/fbr-pos-logo.png')))<img src="{{ asset('assets/img/fbr-pos-logo.png') }}" alt="{{ __('FBR POS') }}" style="height:{{ $qrSize ?? 80 }}px;vertical-align:middle;margin-right:6px">@endif
            @if ($invoice->fbr_qr)
                <img src="data:image/png;base64,{{ $invoice->fbr_qr }}" alt="{{ __('QR') }}" style="width:{{ $qrSize ?? 80 }}px;height:{{ $qrSize ?? 80 }}px;vertical-align:middle">
            @else
                <span id="fbr-qr" data-text="{{ $invoice->fbr_invoice_number }}" style="display:inline-block;vertical-align:middle"></span>
            @endif
            <div style="font-weight:bold">{{ __('FBR Invoice #:') }} {{ $invoice->fbr_invoice_number }}</div>
            <div style="font-size:.85em">{{ __('Verify with the FBR Tax Asaan app or at fbr.gov.pk') }}</div>
        @else
            <div style="font-size:.85em">{{ __('FBR reporting pending') }}</div>
        @endif
    </div>
    @if ($invoice->fbr_status === 'synced' && ! $invoice->fbr_qr)
        <script src="{{ asset('assets/vendor/qrcode/qrcode.js') }}"></script>
        <script>
            (function () {
                var el = document.getElementById('fbr-qr'); if (!el || !window.qrcode) return;
                var qr = qrcode(0, 'M'); qr.addData(el.dataset.text); qr.make();
                el.innerHTML = qr.createImgTag(Math.max(2, Math.floor({{ $qrSize ?? 80 }} / qr.getModuleCount())), 0);
            })();
        </script>
    @endif
@endif
