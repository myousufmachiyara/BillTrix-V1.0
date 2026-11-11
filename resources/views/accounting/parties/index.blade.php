@extends('layouts.app')
@section('title', $type === 'customer' ? 'Customers' : 'Suppliers')
@section('header-actions')
    @can('parties.create')<a href="{{ route('parties.create', ['type' => $type]) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add {{ $type }}</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <div class="card-body pb-0">
        <form class="row g-2 filters" method="GET">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-md-4"><input type="search" name="q" class="form-control form-control-sm" placeholder="Name, phone or code" value="{{ request('q') }}"></div>
            <div class="col-auto"><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="due" value="1" id="due" @checked(request('due'))><label class="form-check-label" for="due">{{ $type === 'customer' ? 'Owes us' : 'We owe' }} only</label></div></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Search</button></div>
        </form>
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Phone</th><th>City</th>@if ($type === 'customer')<th class="num">Credit limit</th>@endif<th class="num">Balance</th></tr></thead>
            <tbody>
            @forelse ($parties as $p)
                <tr data-href="{{ route('parties.show', $p) }}" style="cursor:pointer" class="{{ $p->is_active ? '' : 'text-muted' }}">
                    <td>{{ $p->code }}</td>
                    <td class="fw-semibold">{{ $p->name }} @if ($p->type === 'both')<span class="badge bg-light text-dark">both</span>@endif</td>
                    <td>{{ $p->phone }}</td><td>{{ $p->city }}</td>
                    @if ($type === 'customer')<td class="num">{{ (float) $p->credit_limit ? money($p->credit_limit) : '—' }}</td>@endif
                    <td class="num {{ $p->balance > 0 ? 'text-dr' : ($p->balance < 0 ? 'text-cr' : '') }}">{{ drcr($p->balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-5">No {{ $type }}s yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($parties->hasPages())<div class="card-body">{{ $parties->links() }}</div>@endif
</x-card>
<p class="small text-muted">Dr = they owe you · Cr = you owe them.</p>
@endsection
