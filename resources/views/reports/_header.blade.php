{{-- Printed report heading --}}
<div class="d-none d-print-block text-center mb-3">
    <h3 class="mb-0">{{ $business->name }}</h3>
    <div>{{ $title }}</div>
    @isset($period)<div class="small">{{ $period }}</div>@endisset
</div>
