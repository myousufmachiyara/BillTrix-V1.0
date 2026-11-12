@extends('layouts.app')
@section('title', __('Business & Features'))
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
                    <x-input name="address" label="Address" type="textarea" :value="$b->address" col="col-md-8" />
                    <x-select name="province" label="Province" :options="array_combine(\App\Support\Pakistan::PROVINCES, \App\Support\Pakistan::PROVINCES)" :value="$b->province" placeholder="—" col="col-md-4" />
                </div>
            </x-card>
            <x-card title="Invoice printing">
                <div class="row">
                    <x-select name="invoice_layout" label="Default layout" :options="['a4' => 'A4 page', 'thermal' => 'Thermal receipt']" :value="$b->setting('invoice_layout')" />
                    <x-select name="thermal_width" label="Thermal paper" :options="[80 => '80 mm', 58 => '58 mm']" :value="$b->setting('thermal_width')" />
                    <x-input name="invoice_footer" label="Footer message" type="textarea" :value="$b->setting('invoice_footer')" col="col-12" />
                    <x-input name="quotation_terms" label="Default quotation terms" type="textarea" :value="$b->setting('quotation_terms')" col="col-12" rows="3" />
                    @feature('batches')<x-input name="expiry_alert_days" label="Warn about expiry within (days)" type="number" min="1" :value="$b->setting('expiry_alert_days')" col="col-md-6" />@endfeature
                </div>
            </x-card>
        </div>
        <div class="col-lg-6">
            @if ($b->feature('wht'))
            <x-card title="Withholding tax rates (%)">
                <p class="small text-muted">{{ __('Enter the current rates for your business. Rates change with each Finance Act — confirm with your tax advisor. Non-filer = not on the Active Taxpayers List.') }}</p>
                <div class="row">
                    <x-input name="wht_sales_label" label="On sales — section" :value="$b->setting('wht_sales_label')" col="col-md-4" />
                    <x-input name="wht_sales_filer" label="Filer %" type="number" step="0.001" min="0" :value="$b->setting('wht_sales_filer')" col="col-md-4" />
                    <x-input name="wht_sales_nonfiler" label="Non-filer %" type="number" step="0.001" min="0" :value="$b->setting('wht_sales_nonfiler')" col="col-md-4" />
                    <x-input name="wht_purchase_label" label="On purchases — section" :value="$b->setting('wht_purchase_label')" col="col-md-4" />
                    <x-input name="wht_purchase_filer" label="Filer %" type="number" step="0.001" min="0" :value="$b->setting('wht_purchase_filer')" col="col-md-4" />
                    <x-input name="wht_purchase_nonfiler" label="Non-filer %" type="number" step="0.001" min="0" :value="$b->setting('wht_purchase_nonfiler')" col="col-md-4" />
                </div>
            </x-card>
            @endif
            @can('fbr.manage')
            <x-card title="FBR integration">
                <div class="row">
                    <x-select name="fbr_mode" label="Mode" :options="['off' => 'Off', 'pos' => 'Tier-1 retail POS (IMS)', 'di' => 'Digital Invoicing (sales-tax registered)']" :value="$b->setting('fbr_mode')" col="col-md-6" />
                    <x-select name="fbr_environment" label="Environment" :options="['sandbox' => 'Sandbox (testing)', 'production' => 'Production']" :value="$b->setting('fbr_environment')" col="col-md-6" />
                    <x-input name="fbr_pos_id" label="FBR POS ID (Tier-1)" :value="$b->setting('fbr_pos_id')" col="col-md-6" />
                    <x-input name="fbr_token" label="API token" type="password" col="col-md-6" :help="$b->setting('fbr_token') ? 'A token is saved. Leave blank to keep it.' : 'From IRIS / your FBR POS registration.'" autocomplete="off" />
                    <x-input name="fbr_service_fee" label="POS service fee per invoice (Rs)" type="number" step="0.01" min="0" :value="$b->setting('fbr_service_fee')" col="col-md-6" />
                    <x-input name="fbr_sale_type" label="Default sale type (Digital Invoicing)" :value="$b->setting('fbr_sale_type')" col="col-md-6" />
                    <x-check name="fbr_use_local_fiscal" label="Send through the local FBR fiscal component" :checked="$b->setting('fbr_use_local_fiscal')" col="col-md-6" />
                    <x-input name="fbr_local_url" label="Fiscal component URL" :value="$b->setting('fbr_local_url')" col="col-md-6" />
                </div>
                <p class="small text-muted mb-0">{{ __('Invoices are sent when saved. If FBR can\'t be reached the bill still prints and is retried automatically (') }}<code>{{ __('php artisan billtrix:fbr-sync') }}</code>{{ __(', scheduled every 5 minutes). Your NTN and province above are used as seller details.') }}</p>
            </x-card>
            @endcan
            <x-card title="Features">
                <p class="text-muted">{{ __('Switch on only what your business uses. Everything else stays out of the way.') }}</p>
                <div class="row">
                    @foreach ($features as $key => [$label, $help])
                        <x-check name="features[{{ $key }}]" :label="$label" :help="$help" :checked="$b->feature($key)" col="col-12" />
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
    <div class="text-end mb-4"><button class="btn btn-primary btn-lg"><i class="fas fa-save"></i> {{ __('Save settings') }}</button></div>
</form>
@endsection
