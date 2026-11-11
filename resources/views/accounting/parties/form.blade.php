@extends('layouts.app')
@section('title', $party->exists ? 'Edit '.$party->name : 'Add '.ucfirst($party->type))
@section('content')
<form method="POST" action="{{ $party->exists ? route('parties.update', $party) : route('parties.store') }}">
    @csrf @if ($party->exists) @method('PUT') @endif
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Details">
                <div class="row">
                    <x-input name="name" label="Name" :value="$party->name" required col="col-md-8" />
                    <x-select name="type" label="Type" :options="['customer' => 'Customer', 'supplier' => 'Supplier', 'both' => 'Both']" :value="$party->type" col="col-md-4" />
                    <x-input name="code" label="Code" :value="$party->code" col="col-md-4" help="Leave blank to auto-number." />
                    <x-input name="contact_person" label="Contact person" :value="$party->contact_person" col="col-md-4" />
                    <x-input name="phone" label="Phone" :value="$party->phone" col="col-md-4" />
                    <x-input name="email" label="Email" type="email" :value="$party->email" col="col-md-4" />
                    <x-input name="city" label="City" :value="$party->city" col="col-md-4" />
                    <x-input name="ntn" label="NTN" :value="$party->ntn" col="col-md-4" />
                    <x-input name="address" label="Address" :value="$party->address" col="col-md-8" />
                    <x-input name="cnic" label="CNIC" :value="$party->cnic" col="col-md-4" />
                </div>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Credit & pricing">
                <div class="row">
                    @feature('credit_sales')
                        <x-input name="credit_limit" label="Credit limit" type="number" step="0.01" min="0" :value="(float) $party->credit_limit" col="col-6" help="0 = no limit" />
                        <x-input name="credit_days" label="Credit days" type="number" min="0" :value="$party->credit_days" col="col-6" />
                    @endfeature
                    @feature('price_levels')
                        <x-select name="price_level_id" label="Price level" :options="$priceLevels" :value="$party->price_level_id" placeholder="Default prices" col="col-12" />
                    @endfeature
                    <x-check name="is_active" label="Active" :checked="$party->is_active" col="col-12" />
                </div>
            </x-card>
            <x-card title="Opening balance">
                <div class="row">
                    <x-input name="opening_balance" label="Amount" type="number" step="0.01" min="0" :value="$opening" col="col-7" />
                    <x-select name="opening_side" label="Side" :options="['dr' => 'They owe us', 'cr' => 'We owe them']" :value="$openingSide ?? ($party->type === 'supplier' ? 'cr' : 'dr')" col="col-5" />
                    <x-input name="opening_date" label="As of" type="date" :value="$openingDate ?? now()->toDateString()" col="col-12" required />
                </div>
            </x-card>
        </div>
    </div>
    <div class="text-end mb-4"><a href="{{ url()->previous() }}" class="btn btn-default">Cancel</a> <button class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
</form>
@endsection
