<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /** Boolean feature switches shown on the settings page, with help text. */
    public const FEATURES = [
        'pos' => ['Point of Sale', 'Fast billing screen with barcode scanning and thermal receipts.'],
        'multi_warehouse' => ['Multiple warehouses', 'Keep stock per warehouse / godown and transfer between them.'],
        'multi_unit' => ['Units & packing', 'Buy and sell in cartons, boxes and pieces with automatic conversion.'],
        'price_levels' => ['Price levels', 'Retail, wholesale and other price lists; assign a level to each customer.'],
        'credit_sales' => ['Credit sales', 'Allow sales on account to registered customers.'],
        'credit_limits' => ['Credit limits', 'Block credit sales that would take a customer over their limit.'],
        'allow_negative_stock' => ['Allow negative stock', 'Let sales go ahead even when the system shows no stock.'],
        'tax' => ['Sales tax', 'Show tax rates on products and invoices and post input/output tax.'],
        'prices_include_tax' => ['Prices include tax', 'Entered prices already contain tax (common for retail).'],
        'below_cost_warning' => ['Block below-cost sales', 'Stop selling under average cost unless the user has permission.'],
        'pos_shifts' => ['Register shifts & cash closing', 'Cashiers open a register with a float and close it with a cash count.'],
        'quotations' => ['Quotations', 'Send price quotes and turn them into orders or invoices.'],
        'sales_orders' => ['Sales orders', 'Take orders and deliver or bill them in parts.'],
        'delivery_challans' => ['Delivery challans', 'Dispatch goods on a challan and invoice later (one or several challans per invoice).'],
        'purchase_orders' => ['Purchase orders', 'Order from suppliers and receive against the order in parts.'],
        'customer_prices' => ['Customer-specific prices', 'Agree a special rate per product with a customer.'],
        'wht' => ['Withholding tax', 'Collect income tax on sales and deduct it on purchases at filer / non-filer rates.'],
        'batches' => ['Batches & expiry', 'Track batch numbers and expiry dates for selected products (FEFO picking).'],
    ];

    public function edit(): View
    {
        $this->allow('settings.manage');

        return view('settings.business', [
            'b' => Tenant::business(),
            'features' => self::FEATURES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->allow('settings.manage');
        $business = Tenant::business();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(config('billtrix.categories')))],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'strn' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'features' => ['array'],
            'invoice_layout' => ['required', Rule::in(['a4', 'thermal'])],
            'thermal_width' => ['required', Rule::in([58, 80])],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            'quotation_terms' => ['nullable', 'string', 'max:2000'],
            'province' => ['nullable', Rule::in(\App\Support\Pakistan::PROVINCES)],
            'wht_sales_label' => ['nullable', 'string', 'max:30'],
            'wht_purchase_label' => ['nullable', 'string', 'max:30'],
            'wht_sales_filer' => ['nullable', 'numeric', 'between:0,100'],
            'wht_sales_nonfiler' => ['nullable', 'numeric', 'between:0,100'],
            'wht_purchase_filer' => ['nullable', 'numeric', 'between:0,100'],
            'wht_purchase_nonfiler' => ['nullable', 'numeric', 'between:0,100'],
            'expiry_alert_days' => ['nullable', 'integer', 'between:1,730'],
            'fbr_mode' => ['nullable', Rule::in(['off', 'pos', 'di'])],
            'fbr_environment' => ['nullable', Rule::in(['sandbox', 'production'])],
            'fbr_pos_id' => ['nullable', 'string', 'max:30'],
            'fbr_token' => ['nullable', 'string', 'max:500'],
            'fbr_use_local_fiscal' => ['boolean'],
            'fbr_local_url' => ['nullable', 'url'],
            'fbr_service_fee' => ['nullable', 'numeric', 'between:0,100'],
            'fbr_sale_type' => ['nullable', 'string', 'max:100'],
        ]);

        // Store every switch explicitly so changing the category preset later doesn't flip them.
        $settings = $business->settings ?? [];
        foreach (array_keys(self::FEATURES) as $key) {
            $settings[$key] = (bool) ($data['features'][$key] ?? false);
        }
        $settings['invoice_layout'] = $data['invoice_layout'];
        $settings['thermal_width'] = (int) $data['thermal_width'];
        $settings['invoice_footer'] = $data['invoice_footer'];
        $settings['quotation_terms'] = $data['quotation_terms'] ?? null;
        foreach (['wht_sales_label', 'wht_purchase_label', 'wht_sales_filer', 'wht_sales_nonfiler', 'wht_purchase_filer', 'wht_purchase_nonfiler',
            'expiry_alert_days', 'fbr_environment', 'fbr_pos_id', 'fbr_local_url', 'fbr_service_fee', 'fbr_sale_type'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = is_numeric($data[$key]) ? (float) $data[$key] : $data[$key];
            }
        }
        if (auth()->user()->can('fbr.manage') && array_key_exists('fbr_mode', $data)) {
            $settings['fbr_mode'] = $data['fbr_mode'] ?? 'off';
            $settings['fbr_use_local_fiscal'] = $request->boolean('fbr_use_local_fiscal');
            if (! empty($data['fbr_token'])) {
                $settings['fbr_token'] = Crypt::encryptString($data['fbr_token']); // never stored in plain text
            }
        }

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store("logos/{$business->id}", 'public');
        }

        $business->update(collect($data)->only(['name', 'category', 'legal_name', 'ntn', 'strn', 'phone', 'email', 'address', 'province', 'logo_path'])->all() + ['settings' => $settings]);

        return back()->with('success', __('Settings saved.'));
    }
}
