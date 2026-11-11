@extends('layouts.app')
@section('title', 'Business & Features')
@section('content')
<form method="POST" action="{{ route('settings.business') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="row">
        <div class="col-lg-6">
            <x-card title="Business profile">
                <div class="row">
                    <x-input name="name" label="Business name" :value="$b->name" required />
                    <x-select name="category" label="Business type" :options="config('billtrix.categories')" :value="$b->category" help="Sets defaults only. Feature switches below decide what is on." />
                    <x-input name="legal_name" label="Legal / registered name" :value="$b->legal_name" />
                    <x-input name="phone" label="Phone" :value="$b->phone" />
                    <x-input name="ntn" label="NTN" :value="$b->ntn" />
                    <x-input name="strn" label="STRN (sales tax reg.)" :value="$b->strn" />
                    <x-input name="email" label="Email" type="email" :value="$b->email" />
                    <x-input name="logo" label="Logo (for invoices)" type="file" accept="image/*" />
                    <x-input name="address" label="Address" type="textarea" :value="$b->address" col="col-12" />
                </div>
            </x-card>
            <x-card title="Invoice printing">
                <div class="row">
                    <x-select name="invoice_layout" label="Default layout" :options="['a4' => 'A4 page', 'thermal' => 'Thermal receipt']" :value="$b->setting('invoice_layout')" />
                    <x-select name="thermal_width" label="Thermal paper" :options="[80 => '80 mm', 58 => '58 mm']" :value="$b->setting('thermal_width')" />
                    <x-input name="invoice_footer" label="Footer message" type="textarea" :value="$b->setting('invoice_footer')" col="col-12" />
                    <x-input name="quotation_terms" label="Default quotation terms" type="textarea" :value="$b->setting('quotation_terms')" col="col-12" rows="3" />
                </div>
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card title="Features">
                <p class="text-muted">Switch on only what your business uses. Everything else stays out of the way.</p>
                <div class="row">
                    @foreach ($features as $key => [$label, $help])
                        <x-check name="features[{{ $key }}]" :label="$label" :help="$help" :checked="$b->feature($key)" col="col-12" />
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
    <div class="text-end mb-4"><button class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save settings</button></div>
</form>
@endsection
