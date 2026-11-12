@props(['from' => null, 'to' => null])
<div class="col-auto">
    <label class="form-label small mb-1">{{ __('From') }}</label>
    <input type="date" name="from" value="{{ request('from', $from) }}" class="form-control form-control-sm">
</div>
<div class="col-auto">
    <label class="form-label small mb-1">{{ __('To') }}</label>
    <input type="date" name="to" value="{{ request('to', $to) }}" class="form-control form-control-sm">
</div>
