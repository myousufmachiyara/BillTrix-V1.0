@extends('layouts.app')
@section('title', __('Attributes'))
@section('content')
<div class="row">
    <div class="col-lg-8">
        <x-card title="Attributes">
            <p class="small text-muted">{{ __('Attributes are the options products vary by, such as Size or Colour. Pick them on a product to create its variations.') }}</p>
            <table class="table table-sm align-middle">
                <thead><tr><th>{{ __('Attribute') }}</th><th>{{ __('Values') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @forelse ($attributes as $a)
                    <tr>
                        <td class="fw-semibold">{{ $a->name }}</td>
                        <td>@foreach ($a->values as $v)<span class="badge bg-light text-dark border me-1 mb-1" title="{{ $v->variants_count }} {{ __('variations') }}">{{ $v->value }}@if ($v->code) <span class="text-muted">· {{ $v->code }}</span>@endif</span>@endforeach</td>
                        <td class="text-end text-nowrap">
                            @can('products.edit')
                                <button type="button" class="btn btn-sm btn-link p-0 me-2" data-bs-toggle="collapse" data-bs-target="#edit-{{ $a->id }}" title="{{ __('Edit') }}"><i class="fas fa-edit"></i></button>
                                <x-delete :action="route('attributes.destroy', $a)" />
                            @endcan
                        </td>
                    </tr>
                    @can('products.edit')
                    <tr class="collapse" id="edit-{{ $a->id }}"><td colspan="3">
                        <form method="POST" action="{{ route('attributes.update', $a) }}" class="row g-2">@csrf @method('PUT')
                            <div class="col-md-3"><input name="name" class="form-control form-control-sm" value="{{ $a->name }}" required></div>
                            <div class="col-md-6"><input name="values" class="form-control form-control-sm" value="{{ $a->values->map(fn ($v) => $v->code ? $v->value.'='.$v->code : $v->value)->implode(', ') }}" required></div>
                            <div class="col-md-1"><input name="sort" type="number" min="0" class="form-control form-control-sm" value="{{ $a->sort }}" title="{{ __('Order in variation names') }}"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ __('Save') }}</button></div>
                        </form>
                    </td></tr>
                    @endcan
                @empty
                    <tr><td colspan="3" class="text-muted">{{ __('No attributes yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
    @can('products.edit')
    <div class="col-lg-4">
        <x-card title="Add attribute">
            <form method="POST" action="{{ route('attributes.store') }}" class="row">@csrf
                <x-input name="name" label="Attribute" placeholder="e.g. Size" required col="col-12" />
                <x-input name="values" label="Values" type="textarea" placeholder="S, M, L, XL" required col="col-12"
                    help="Separate with commas or new lines. Optional short code for SKUs: Red=RD." />
                <x-input name="sort" label="Order in variation names" type="number" min="0" :value="0" col="col-12" />
                <div class="col-12"><button class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('Add') }}</button></div>
            </form>
        </x-card>
    </div>
    @endcan
</div>
@endsection
