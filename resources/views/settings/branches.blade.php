@extends('layouts.app')
@section('title', __('Branches & Warehouses'))
@section('content')
<div class="row">
    <div class="col-lg-8">
        @foreach ($branches as $branch)
            <x-card :title="$branch->name.' ('.$branch->code.')'.($branch->is_active ? '' : ' — inactive')">
                <form method="POST" action="{{ route('settings.branches.update', $branch) }}" class="row g-2 mb-3">
                    @csrf @method('PUT')
                    <div class="col-md-4"><input name="name" class="form-control form-control-sm" value="{{ $branch->name }}" required></div>
                    <div class="col-md-2"><input name="code" class="form-control form-control-sm" value="{{ $branch->code }}" required></div>
                    <div class="col-md-3"><input name="phone" class="form-control form-control-sm" value="{{ $branch->phone }}" placeholder="{{ __('Phone') }}"></div>
                    <div class="col-md-3"><input name="address" class="form-control form-control-sm" value="{{ $branch->address }}" placeholder="{{ __('Address') }}"></div>
                    <div class="col-md-6"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($branch->is_active) id="ba{{ $branch->id }}"><label class="form-check-label" for="ba{{ $branch->id }}">{{ __('Active') }}</label></div></div>
                    <div class="col-md-6 text-end">
                        @can('branches.edit')<button class="btn btn-sm btn-primary">{{ __('Save branch') }}</button>@endcan
                    </div>
                </form>
                @can('branches.delete')
                    <div class="text-end mb-2"><x-delete :action="route('settings.branches.destroy', $branch)" label="Delete branch" /></div>
                @endcan

                <h5 class="mb-2">{{ __('Warehouses') }}</h5>
                <table class="table table-sm">
                    @foreach ($branch->warehouses as $wh)
                        <tr><td colspan="4">
                            <form method="POST" action="{{ route('settings.warehouses.update', $wh) }}" class="row g-2 align-items-center">
                                @csrf @method('PUT')
                                <div class="col-md-5"><input name="name" class="form-control form-control-sm" value="{{ $wh->name }}" required></div>
                                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($wh->is_active) id="wa{{ $wh->id }}"><label class="form-check-label" for="wa{{ $wh->id }}">{{ __('Active') }}</label></div></div>
                                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" @checked($wh->is_default) id="wd{{ $wh->id }}"><label class="form-check-label" for="wd{{ $wh->id }}">{{ __('Default') }}</label></div></div>
                                <div class="col text-end">@can('branches.edit')<button class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>@endcan</div>
                            </form>
                        </td></tr>
                    @endforeach
                </table>
                @can('branches.create')
                    @feature('multi_warehouse')
                        <form method="POST" action="{{ route('settings.warehouses.store', $branch) }}" class="row g-2">
                            @csrf
                            <div class="col-md-5"><input name="name" class="form-control form-control-sm" placeholder="{{ __('New warehouse / godown name') }}" required></div>
                            <div class="col-auto"><button class="btn btn-sm btn-success"><i class="fas fa-plus"></i> {{ __('Add warehouse') }}</button></div>
                        </form>
                    @endfeature
                @endcan
            </x-card>
        @endforeach
    </div>
    <div class="col-lg-4">
        @can('branches.create')
            <x-card title="Add branch">
                <form method="POST" action="{{ route('settings.branches.store') }}" class="row">
                    @csrf
                    <x-input name="name" label="Branch name" col="col-12" required />
                    <x-input name="code" label="Code" col="col-6" required maxlength="10" />
                    <x-input name="phone" label="Phone" col="col-6" />
                    <x-input name="address" label="Address" col="col-12" />
                    <input type="hidden" name="is_active" value="1">
                    <div class="col-12"><button class="btn btn-primary w-100">{{ __('Add branch') }}</button></div>
                </form>
            </x-card>
        @endcan
    </div>
</div>
@endsection
