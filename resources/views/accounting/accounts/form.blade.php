@extends('layouts.app')
@section('title', $account->exists ? __('Edit Account') : __('Add Account'))
@section('content')
<div class="row"><div class="col-lg-7">
<x-card>
    <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}" class="row">
        @csrf @if ($account->exists) @method('PUT') @endif
        <div class="col-md-12 mb-3">
            <label class="form-label">{{ __('Under group') }} <span class="text-danger">*</span></label>
            <select name="parent_id" class="form-select select2" required @disabled($account->system_key)>
                @foreach ($groups as $g)
                    <option value="{{ $g->id }}" @selected((int) old('parent_id', $account->parent_id) === $g->id)>{{ $g->code }} — {{ $g->name }} ({{ __(\App\Models\Account::TYPES[$g->type]) }})</option>
                @endforeach
            </select>
            @if ($account->system_key)<input type="hidden" name="parent_id" value="{{ $account->parent_id }}">@endif
            <div class="form-text">{{ __('The account takes its nature (asset, liability, income…) from its group.') }}</div>
        </div>
        <x-input name="code" label="Code" :value="$account->code" required col="col-md-4" />
        <x-input name="name" label="Name" :value="$account->name" required col="col-md-8" />
        <x-select name="subtype" label="Cash / bank account?" :options="['cash' => 'Cash', 'bank' => 'Bank']" :value="$account->subtype" placeholder="No" help="Cash and bank accounts appear in receipts, payments and invoices." />
        <div class="col-md-6 pt-4">
            <x-check name="is_group" label="Group (holds sub-accounts)" :checked="$account->is_group" col="col-12 mb-1" />
            <x-check name="is_active" label="Active" :checked="$account->is_active ?? true" col="col-12" />
        </div>
        <x-input name="description" label="Description" :value="$account->description" col="col-12" />
        <div class="col-12 d-flex justify-content-between">
            <div>@if ($account->exists) @can('accounts.delete') <x-delete :action="route('accounts.destroy', $account)" label="Delete" /> @endcan @endif</div>
            <div><a href="{{ route('accounts.index') }}" class="btn btn-default">{{ __('Cancel') }}</a> <button class="btn btn-primary">{{ __('Save') }}</button></div>
        </div>
    </form>
</x-card>
</div></div>
@endsection
