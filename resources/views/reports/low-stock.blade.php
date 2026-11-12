@extends('layouts.app')
@section('title', __('Low Stock / Reorder'))
@section('content')
@include('reports._header', ['title' => 'Low Stock / Reorder'])
<x-card :body="false">
    <x-slot:actions><button type="button" onclick="window.print()" class="btn btn-sm btn-default no-print"><i class="fas fa-print"></i></button> <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-sm btn-default no-print"><i class="fas fa-file-excel text-success"></i></a></x-slot:actions>
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th class="num">{{ __('In stock') }}</th><th class="num">{{ __('Reorder level') }}</th><th class="num">{{ __('Short by') }}</th></tr></thead>
        <tbody>
        @forelse ($rows as $p)
            <tr><td><a href="{{ route('products.show', $p->id) }}">{{ $p->name }}</a></td><td>{{ $p->sku }}</td><td class="num text-danger">{{ qty($p->qty) }} {{ $p->unit->short_name }}</td><td class="num">{{ qty($p->reorder_level) }}</td><td class="num">{{ qty($p->reorder_level - $p->qty) }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Everything is above its reorder level.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</x-card>
@endsection
