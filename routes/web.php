<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,
    // Accounts
    HeadOfAccountsController,
    SubHeadOfAccController,
    COAController,
    // Users / Roles / Permissions
    UserController,
    RoleController,
    PermissionController,
    // Products
    ProductCategoryController,
    ProductSubcategoryController,
    AttributeController,
    ProductController,
    // Purchase
    PurchaseOrderController,
    PurchaseInvoiceController,
    PurchaseReturnController,
    // Production
    ProductionOrderController,
    ProductionReceivingController,
    ProductionReturnController,
    // Inventory
    LocationController,
    StockInOutController,
    GatepassController,
    StockTransferController,
    // Branches
    BranchController,
    BranchUserController,
    // Sales
    QuotationController,
    SaleOrderController,
    SaleInvoiceController,
    SaleReturnController,
    // Finance
    VoucherController,
    PdcController,
    // Reports
    InventoryReportController,
    PurchaseReportController,
    ProductionReportController,
    SalesReportController,
    AccountsReportController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ── User helpers ──────────────────────────────────────────────────────────
    Route::put('/users/{id}/change-password', [UserController::class, 'changePassword'])->name('users.changePassword');
    Route::put('/users/{id}/toggle-active',   [UserController::class, 'toggleActive'])  ->name('users.toggleActive');

    // ── Product helpers ───────────────────────────────────────────────────────
    Route::get('/products/details',                [ProductController::class,         'details'])          ->name('products.details');
    Route::get('/product/{product}/variations',    [ProductController::class,         'getVariations'])    ->name('product.variations');
    Route::get('/products/{product}/barcodes',     [ProductController::class,         'printBarcodes'])    ->name('products.barcodes');
    Route::get('/get-subcategories/{category_id}', [ProductCategoryController::class, 'getSubcategories'])->name('products.getSubcategories');

    // ── Purchase helpers ──────────────────────────────────────────────────────
    Route::get('/purchase-invoices/download-template', [PurchaseInvoiceController::class, 'downloadTemplate'])    ->name('purchase_invoices.download_template');
    Route::post('/purchase-invoices/import',           [PurchaseInvoiceController::class, 'import'])              ->name('purchase_invoices.import');
    Route::get('/purchase-invoices/{id}/barcodes',     [PurchaseInvoiceController::class, 'printBarcodes'])       ->name('purchase_invoices.barcodes');
    Route::get('/product/{product}/invoices',          [PurchaseInvoiceController::class, 'getProductInvoices'])  ->name('product.invoices');

    // ── Production helpers ────────────────────────────────────────────────────
    Route::get('/production-orders/{id}/summary',  [ProductionOrderController::class, 'summary'])              ->name('production_orders.summary');
    Route::get('/production-orders/{id}/gatepass', [ProductionOrderController::class, 'printGatepass'])        ->name('production_orders.gatepass');
    Route::get('/product/{product}/productions',   [ProductionOrderController::class, 'getProductProductions'])->name('product.productions');

    // ── Sale helpers ──────────────────────────────────────────────────────────
    Route::get('/sale-invoices/scan-barcode',         [SaleInvoiceController::class, 'scanBarcode'])     ->name('sale_invoices.scan_barcode');
    Route::get('/sale-invoices/{id}/thermal-print',   [SaleInvoiceController::class, 'thermalPrint'])    ->name('sale_invoices.thermal_print');

    // ── Vouchers (typed routes) ───────────────────────────────────────────────
    // Voucher types: payment | receipt | journal | contra
    Route::redirect('/vouchers', '/vouchers/payment')->name('vouchers.default');

    Route::prefix('vouchers/{type}')->group(function () {
        Route::get('/',           [VoucherController::class, 'index'])  ->middleware('check.permission:vouchers.index')  ->name('vouchers.index');
        Route::get('/create',     [VoucherController::class, 'create']) ->middleware('check.permission:vouchers.create') ->name('vouchers.create');
        Route::post('/',          [VoucherController::class, 'store'])  ->middleware('check.permission:vouchers.create') ->name('vouchers.store');
        Route::get('/{id}',       [VoucherController::class, 'show'])   ->middleware('check.permission:vouchers.index')  ->name('vouchers.show');
        Route::get('/{id}/edit',  [VoucherController::class, 'edit'])   ->middleware('check.permission:vouchers.edit')   ->name('vouchers.edit');
        Route::put('/{id}',       [VoucherController::class, 'update']) ->middleware('check.permission:vouchers.edit')   ->name('vouchers.update');
        Route::delete('/{id}',    [VoucherController::class, 'destroy'])->middleware('check.permission:vouchers.delete') ->name('vouchers.destroy');
        Route::get('/{id}/print', [VoucherController::class, 'print'])  ->middleware('check.permission:vouchers.print')  ->name('vouchers.print');
    });

    // ── Standard CRUD modules ─────────────────────────────────────────────────
    // Each entry: 'uri' => ['controller', 'permission_prefix', optional 'param']
    // The loop registers: index, create, store, show, edit, update, destroy, print
    // Route names match sidebar exactly: {uri}.index, {uri}.create, etc.
    $modules = [
        // 1. Accounts
        'hoa'                   => ['controller' => HeadOfAccountsController::class,    'permission' => 'heads_of_accounts'],
        'shoa'                  => ['controller' => SubHeadOfAccController::class,      'permission' => 'sub_heads'],
        'coa'                   => ['controller' => COAController::class,               'permission' => 'coa'],

        // 2. Users / Roles / Permissions
        'users'                 => ['controller' => UserController::class,              'permission' => 'users'],
        'roles'                 => ['controller' => RoleController::class,              'permission' => 'roles',                 'param' => '{role}'],
        'permissions'           => ['controller' => PermissionController::class,        'permission' => 'permissions'],

        // 3. Products
        'product_categories'    => ['controller' => ProductCategoryController::class,   'permission' => 'product_categories'],
        'product_subcategories' => ['controller' => ProductSubcategoryController::class,'permission' => 'product_subcategories'],
        'attributes'            => ['controller' => AttributeController::class,         'permission' => 'attributes'],
        'products'              => ['controller' => ProductController::class,           'permission' => 'products'],

        // 4. Purchase
        'purchase_orders'       => ['controller' => PurchaseOrderController::class,    'permission' => 'purchase_orders'],
        'purchase_invoices'     => ['controller' => PurchaseInvoiceController::class,  'permission' => 'purchase_invoices'],
        'purchase_returns'      => ['controller' => PurchaseReturnController::class,   'permission' => 'purchase_returns'],

        // 5. Production
        'production_orders'     => ['controller' => ProductionOrderController::class,    'permission' => 'production_orders'],
        'production_receiving'  => ['controller' => ProductionReceivingController::class,'permission' => 'production_receiving'],
        'production_returns'    => ['controller' => ProductionReturnController::class,   'permission' => 'production_returns'],

        // 6. Inventory
        'locations'             => ['controller' => LocationController::class,          'permission' => 'locations'],
        'stock_in_out'          => ['controller' => StockInOutController::class,        'permission' => 'stock_in_out'],
        'gatepasses'            => ['controller' => GatepassController::class,          'permission' => 'gatepasses'],
        'stock_transfers'       => ['controller' => StockTransferController::class,     'permission' => 'stock_transfers'],

        // 7. Branches
        'branches'              => ['controller' => BranchController::class,            'permission' => 'branches'],
        'branch_users'          => ['controller' => BranchUserController::class,        'permission' => 'branch_users'],

        // 8. Sales
        'quotations'            => ['controller' => QuotationController::class,         'permission' => 'quotations'],
        'sale_orders'           => ['controller' => SaleOrderController::class,         'permission' => 'sale_orders'],
        'sale_invoices'         => ['controller' => SaleInvoiceController::class,       'permission' => 'sale_invoices'],
        'sale_returns'          => ['controller' => SaleReturnController::class,        'permission' => 'sale_returns'],

        // 9. Finance
        'pdc'                   => ['controller' => PdcController::class,               'permission' => 'pdc'],
    ];

    foreach ($modules as $uri => $config) {
        $controller = $config['controller'];
        $permission = $config['permission'];
        $param      = $config['param'] ?? '{id}';

        Route::get   ("$uri",              [$controller, 'index'])  ->middleware("check.permission:$permission.index")  ->name("$uri.index");
        Route::get   ("$uri/create",       [$controller, 'create']) ->middleware("check.permission:$permission.create") ->name("$uri.create");
        Route::post  ("$uri",              [$controller, 'store'])  ->middleware("check.permission:$permission.create") ->name("$uri.store");
        Route::get   ("$uri/$param",       [$controller, 'show'])   ->middleware("check.permission:$permission.index")  ->name("$uri.show");
        Route::get   ("$uri/$param/edit",  [$controller, 'edit'])   ->middleware("check.permission:$permission.edit")   ->name("$uri.edit");
        Route::put   ("$uri/$param",       [$controller, 'update']) ->middleware("check.permission:$permission.edit")   ->name("$uri.update");
        Route::delete("$uri/$param",       [$controller, 'destroy'])->middleware("check.permission:$permission.delete") ->name("$uri.destroy");
        Route::get   ("$uri/$param/print", [$controller, 'print'])  ->middleware("check.permission:$permission.print")  ->name("$uri.print");
    }

    // ── 10. Reports ───────────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {

        // Inventory sub-group
        Route::get('inventory',     [InventoryReportController::class, 'index'])       ->middleware('check.permission:reports.inventory')     ->name('inventory');
        Route::get('item-ledger',   [InventoryReportController::class, 'itemLedger'])  ->middleware('check.permission:reports.item_ledger')   ->name('item_ledger');
        Route::get('stock-in-hand', [InventoryReportController::class, 'stockInHand']) ->middleware('check.permission:reports.stock_in_hand') ->name('stock_in_hand');

        // Operational reports
        Route::get('purchase',      [PurchaseReportController::class,   'index'])      ->middleware('check.permission:reports.purchase')      ->name('purchase');
        Route::get('production',    [ProductionReportController::class, 'index'])      ->middleware('check.permission:reports.production')    ->name('production');
        Route::get('sales',         [SalesReportController::class,      'index'])      ->middleware('check.permission:reports.sales')         ->name('sales');

        // Accounting reports
        Route::get('ledger',        [AccountsReportController::class, 'ledger'])       ->middleware('check.permission:reports.ledger')        ->name('ledger');
        Route::get('trial-balance', [AccountsReportController::class, 'trialBalance']) ->middleware('check.permission:reports.trial_balance') ->name('trial_balance');
        Route::get('pnl',           [AccountsReportController::class, 'pnl'])          ->middleware('check.permission:reports.pnl')           ->name('pnl');
        Route::get('payables',      [AccountsReportController::class, 'payables'])     ->middleware('check.permission:reports.payables')      ->name('payables');
        Route::get('receivables',   [AccountsReportController::class, 'receivables'])  ->middleware('check.permission:reports.receivables')   ->name('receivables');

        // Additional accounting/summary reports
        Route::get('cash-flow',     [AccountsReportController::class, 'cashFlow'])     ->middleware('check.permission:reports.cash_flow')     ->name('cash_flow');
        Route::get('balance-sheet', [AccountsReportController::class, 'balanceSheet']) ->middleware('check.permission:reports.balance_sheet') ->name('balance_sheet');
        Route::get('day-book',      [AccountsReportController::class, 'dayBook'])      ->middleware('check.permission:reports.day_book')      ->name('day_book');

        // AJAX endpoints for report filters (no permission gate — already inside auth)
        Route::get('filter/accounts', [AccountsReportController::class, 'filterAccounts'])->name('filter.accounts');
        Route::get('filter/products', [InventoryReportController::class, 'filterProducts'])->name('filter.products');
    });
});