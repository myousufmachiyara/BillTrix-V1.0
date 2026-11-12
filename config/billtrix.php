<?php

/*
|--------------------------------------------------------------------------
| BillTrix generic edition configuration
|--------------------------------------------------------------------------
| Business categories are presets for the feature switches below, nothing
| more. Every switch can be changed per business in Settings, so no feature
| is hard-wired to a category.
*/

return [

    'allow_registration' => env('BILLTRIX_ALLOW_REGISTRATION', false),

    'categories' => [
        'retail' => 'Retailer',
        'wholesale' => 'Wholesaler',
        'trading' => 'Trader',
    ],

    // Default value of every per-business setting.
    'settings' => [
        'pos' => true,
        'multi_warehouse' => false,
        'multi_unit' => false,
        'price_levels' => false,
        'credit_sales' => true,
        'credit_limits' => false,
        'allow_negative_stock' => false,
        'tax' => false,
        'prices_include_tax' => false,
        'below_cost_warning' => true,
        'invoice_layout' => 'a4',      // a4 | thermal
        'thermal_width' => 80,         // mm
        'invoice_footer' => 'Thank you for your business.',
        'decimals_qty' => 2,
        'payment_methods' => ['cash', 'bank', 'card', 'jazzcash', 'easypaisa'],
        'quotations' => false,
        'sales_orders' => false,
        'purchase_orders' => false,
        'delivery_challans' => false,
        'customer_prices' => false,
        'pos_shifts' => false,
        // Withholding tax (income tax collected on sales / deducted on purchases). Rates are % and set per business.
        'wht' => false,
        'wht_sales_label' => '236G / 236H',
        'wht_sales_filer' => 0,
        'wht_sales_nonfiler' => 0,
        'wht_purchase_label' => '153',
        'wht_purchase_filer' => 0,
        'wht_purchase_nonfiler' => 0,
        // FBR: off | pos (Tier-1 retail, IMS) | di (Digital Invoicing for sales-tax registered sellers)
        'fbr_mode' => 'off',
        'fbr_environment' => 'sandbox',
        'fbr_pos_id' => null,
        'fbr_token' => null,           // stored encrypted
        'fbr_use_local_fiscal' => false,
        'fbr_local_url' => 'http://localhost:8524/api/IMSFiscal/GetInvoiceNumberByModel',
        'fbr_service_fee' => 1,        // Re 1 POS service fee per invoice
        'fbr_sale_type' => 'Goods at standard rate (default)',
        'batches' => false,
        'variants' => false,         // product variations: size, colour, … with stock per variation
        'expiry_alert_days' => 60,
        'quotation_terms' => "Prices valid for 7 days.\nDelivery within 3 working days of confirmation.",
    ],

    'presets' => [
        'retail' => ['pos' => true, 'pos_shifts' => true, 'invoice_layout' => 'thermal', 'credit_limits' => false],
        'wholesale' => ['pos' => false, 'multi_unit' => true, 'price_levels' => true, 'credit_limits' => true, 'multi_warehouse' => true,
            'quotations' => true, 'sales_orders' => true, 'purchase_orders' => true, 'delivery_challans' => true, 'customer_prices' => true],
        'trading' => ['pos' => false, 'multi_unit' => true, 'credit_limits' => true, 'quotations' => true, 'sales_orders' => true,
            'purchase_orders' => true, 'customer_prices' => true],
    ],

    // Document number prefixes.
    'sequences' => [
        'sale' => 'SI',
        'purchase' => 'PI',
        'sale_return' => 'SR',
        'purchase_return' => 'PR',
        'receipt' => 'RV',
        'payment' => 'PV',
        'journal' => 'JV',
        'contra' => 'CV',
        'opening' => 'OB',
        'stock_adjustment' => 'SA',
        'stock_transfer' => 'ST',
        'invoice_entry' => 'IJ',
        'stock_entry' => 'SJ',
        'quotation' => 'QT',
        'sale_order' => 'SO',
        'purchase_order' => 'PO',
        'delivery' => 'DC',
        'cash_session' => 'CS',
        'customer' => 'C',
        'supplier' => 'S',
    ],

    /*
    | Default chart of accounts for a new business. Pakistani SME friendly.
    | [code, name, type, subtype, system_key, children]
    */
    'chart_of_accounts' => [
        ['1', 'Assets', 'asset', null, null, [
            ['11', 'Current Assets', 'asset', null, null, [
                ['1101', 'Cash in Hand', 'asset', 'cash', 'cash'],
                ['1102', 'Bank Accounts', 'asset', null, null, [
                    ['110201', 'Main Bank Account', 'asset', 'bank', 'bank'],
                ]],
                ['1103', 'Accounts Receivable', 'asset', 'receivable', 'receivable'],
                ['1104', 'Stock in Hand', 'asset', 'inventory', 'inventory'],
                ['1105', 'Input Sales Tax', 'asset', 'tax', 'tax_input'],
                ['1106', 'Advances & Deposits', 'asset', null, null],
                ['1107', 'Goods Delivered Not Invoiced', 'asset', 'inventory', 'gdni'],
                ['1108', 'Advance Income Tax (deducted by customers)', 'asset', 'tax', 'wht_receivable'],
            ]],
            ['12', 'Fixed Assets', 'asset', null, null, [
                ['1201', 'Furniture & Fixtures', 'asset', null, null],
                ['1202', 'Vehicles', 'asset', null, null],
                ['1203', 'Computers & Equipment', 'asset', null, null],
            ]],
        ]],
        ['2', 'Liabilities', 'liability', null, null, [
            ['21', 'Current Liabilities', 'liability', null, null, [
                ['2101', 'Accounts Payable', 'liability', 'payable', 'payable'],
                ['2102', 'Output Sales Tax', 'liability', 'tax', 'tax_output'],
                ['2103', 'Accrued Expenses', 'liability', null, null],
                ['2104', 'Withholding Tax Payable', 'liability', 'tax', 'wht_payable'],
                ['2105', 'FBR POS Fee Payable', 'liability', 'tax', 'pos_fee_payable'],
            ]],
            ['22', 'Loans', 'liability', null, null, [
                ['2201', 'Bank Loans', 'liability', null, null],
            ]],
        ]],
        ['3', 'Equity', 'equity', null, null, [
            ['3101', "Owner's Capital", 'equity', null, 'capital'],
            ['3102', "Owner's Drawings", 'equity', null, 'drawings'],
            ['3103', 'Retained Earnings', 'equity', null, 'retained_earnings'],
            ['3104', 'Opening Balance Equity', 'equity', null, 'opening_equity'],
        ]],
        ['4', 'Income', 'income', null, null, [
            ['4101', 'Sales', 'income', null, 'sales'],
            ['4102', 'Sales Returns', 'income', null, 'sales_returns'],
            ['4103', 'Sales Discounts', 'income', null, 'sales_discount'],
            ['4201', 'Other Income', 'income', null, 'other_income'],
            ['4202', 'Freight Recovered', 'income', null, 'freight_income'],
        ]],
        ['5', 'Expenses', 'expense', null, null, [
            ['51', 'Cost of Sales', 'expense', null, null, [
                ['5101', 'Cost of Goods Sold', 'expense', null, 'cogs'],
                ['5102', 'Inventory Adjustments', 'expense', null, 'stock_adjustment'],
                ['5103', 'Freight Inward', 'expense', null, 'freight_inward'],
            ]],
            ['52', 'Operating Expenses', 'expense', null, null, [
                ['5201', 'Salaries & Wages', 'expense', null, null],
                ['5202', 'Rent', 'expense', null, null],
                ['5203', 'Electricity & Utilities', 'expense', null, null],
                ['5204', 'Telephone & Internet', 'expense', null, null],
                ['5205', 'Fuel & Transport', 'expense', null, null],
                ['5206', 'Repairs & Maintenance', 'expense', null, null],
                ['5207', 'Bank Charges', 'expense', null, null],
                ['5208', 'Miscellaneous Expenses', 'expense', null, null],
                ['5209', 'Cash Over / Short', 'expense', null, 'cash_over_short'],
            ]],
        ]],
    ],

    'units' => [
        ['Piece', 'pcs', false], ['Dozen', 'dzn', false], ['Box', 'box', false], ['Carton', 'ctn', false],
        ['Packet', 'pkt', false], ['Kilogram', 'kg', true], ['Gram', 'g', true], ['Litre', 'ltr', true],
        ['Metre', 'm', true], ['Bag', 'bag', false],
    ],

    // Permission modules and their actions. Roles are per business (spatie teams).
    'permissions' => [
        'dashboard' => ['view'],
        'settings' => ['manage'],
        'users' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'branches' => ['view', 'create', 'edit', 'delete'],
        'accounts' => ['view', 'create', 'edit', 'delete'],
        'parties' => ['view', 'create', 'edit', 'delete'],
        'vouchers' => ['view', 'create', 'edit', 'delete'],
        'products' => ['view', 'create', 'edit', 'delete', 'labels'],
        'stock' => ['view', 'adjust', 'transfer'],
        'sales' => ['view', 'create', 'edit', 'delete', 'override_credit_limit', 'below_cost'],
        'sale_returns' => ['view', 'create', 'delete'],
        'purchases' => ['view', 'create', 'edit', 'delete'],
        'purchase_returns' => ['view', 'create', 'delete'],
        'pos' => ['use'],
        'cash_sessions' => ['view', 'manage'],
        'quotations' => ['view', 'create', 'edit', 'delete'],
        'sales_orders' => ['view', 'create', 'edit', 'delete'],
        'purchase_orders' => ['view', 'create', 'edit', 'delete'],
        'deliveries' => ['view', 'create', 'edit', 'delete'],
        'reports' => ['sales', 'purchases', 'inventory', 'accounts', 'profit', 'tax'],
        'imports' => ['products', 'parties'],
        'api' => ['use'],
        'fbr' => ['manage'],
        'audit' => ['view'],
        'backups' => ['download'],
    ],

    'roles' => [
        'Owner' => '*',
        'Manager' => ['dashboard.*', 'parties.*', 'vouchers.*', 'products.*', 'stock.*', 'sales.*', 'sale_returns.*', 'purchases.*', 'purchase_returns.*', 'pos.*', 'reports.*', 'accounts.view',
            'cash_sessions.*', 'quotations.*', 'sales_orders.*', 'purchase_orders.*', 'deliveries.*', 'imports.*', 'api.use', 'fbr.manage'],
        'Accountant' => ['dashboard.view', 'accounts.*', 'parties.*', 'vouchers.*', 'reports.*', 'sales.view', 'purchases.view', 'products.view', 'stock.view', 'cash_sessions.view', 'imports.parties', 'fbr.manage'],
        'Cashier' => ['pos.use', 'sales.view', 'sales.create', 'sale_returns.view', 'sale_returns.create', 'parties.view', 'parties.create', 'products.view', 'quotations.view', 'quotations.create', 'api.use'],
        'Storekeeper' => ['products.view', 'products.labels', 'stock.*', 'purchases.view', 'reports.inventory', 'deliveries.*', 'sales_orders.view', 'purchase_orders.view'],
    ],
];
