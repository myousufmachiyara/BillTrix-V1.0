@extends('layouts.app')
@section('title', __('Close Register').' '.$session->number)
@section('content')
<form method="POST" action="{{ route('cash-sessions.close', $session) }}" id="close-form">
    @csrf @method('PUT')
    <div class="row">
        <div class="col-lg-6">
            <x-card title="Shift summary" :body="false">
                <div class="card-body py-2 small text-muted">{{ $session->user->name }} {{ __('· opened') }} {{ $session->opened_at->format('d M Y H:i') }}</div>
                @include('cash-sessions._summary')
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card title="Count the cash">
                <table class="table table-sm" id="denoms">
                    <thead><tr><th>{{ __('Note / coin') }}</th><th style="width:120px">{{ __('Count') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                    @foreach (\App\Models\CashSession::DENOMINATIONS as $d)
                        <tr><td>{{ __('Rs') }} {{ number_format($d) }}</td><td><input type="number" min="0" step="1" name="denominations[{{ $d }}]" data-d="{{ $d }}" class="form-control form-control-sm text-end"></td><td class="num" data-amt>0</td></tr>
                    @endforeach
                    </tbody>
                </table>
                <label class="form-label">{{ __('Counted cash') }}</label>
                <input type="number" step="0.01" min="0" name="counted_cash" id="counted" class="form-control form-control-lg text-end mb-2" required>
                <div class="d-flex justify-content-between fs-5 mb-3"><span>{{ __('Over / short') }}</span><strong id="diff">—</strong></div>
                <x-input name="notes" label="Notes" type="textarea" col="col-12 px-0" placeholder="Explain any difference" />
                <button class="btn btn-warning btn-lg w-100" onclick="return confirm(@js(__('Close this register? This cannot be undone.')))"><i class="fas fa-lock"></i> {{ __('Close register') }}</button>
            </x-card>
        </div>
    </div>
</form>
@endsection
@push('scripts')
<script>
(function ($) {
    const expected = @js($summary['expected_cash']);
    function calc(fromDenoms) {
        if (fromDenoms) {
            let total = 0;
            $('[data-d]').each(function () { const a = BT.num(this.value) * +$(this).data('d'); total += a; $(this).closest('tr').find('[data-amt]').text(BT.money(a)); });
            $('#counted').val(total.toFixed(2));
        }
        const diff = BT.round(BT.num($('#counted').val()) - expected);
        $('#diff').text(($('#counted').val() === '' ? '—' : (diff > 0 ? '+' : '') + BT.money(diff))).toggleClass('text-danger', diff < 0).toggleClass('text-success', diff > 0);
    }
    $(document).on('input', '[data-d]', () => calc(true));
    $('#counted').on('input', () => calc(false));
})(jQuery);
</script>
@endpush
