<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\PartyController;
use App\Http\Controllers\Accounting\PartyPriceController;
use App\Http\Controllers\Accounting\VoucherController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Settings\BranchController;
use App\Http\Controllers\Settings\BusinessController;
use App\Http\Controllers\Settings\MasterDataController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Trade\CashSessionController;
use App\Http\Controllers\Trade\DeliveryController;
use App\Http\Controllers\Trade\InvoiceController;
use App\Http\Controllers\Trade\OrderController;
use App\Http\Controllers\Trade\PosController;
use Illuminate\Support\Facades\Route;

// ── Guest ──────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

// ── Signed in ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::put('my-password', [AuthController::class, 'changePassword'])->name('password.change');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('business', [BusinessController::class, 'edit'])->name('business');
        Route::put('business', [BusinessController::class, 'update']);
        Route::resource('branches', BranchController::class)->except(['show', 'create', 'edit']);
        Route::post('branches/{branch}/warehouses', [BranchController::class, 'storeWarehouse'])->name('warehouses.store');
        Route::put('warehouses/{warehouse}', [BranchController::class, 'updateWarehouse'])->name('warehouses.update');
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    // Simple lookup lists: units, categories, price levels, tax rates.
    Route::get('masters/{master}', [MasterDataController::class, 'index'])->name('masters.index');
    Route::post('masters/{master}', [MasterDataController::class, 'store'])->name('masters.store');
    Route::put('masters/{master}/{id}', [MasterDataController::class, 'update'])->name('masters.update');
    Route::delete('masters/{master}/{id}', [MasterDataController::class, 'destroy'])->name('masters.destroy');

    // Accounting
    Route::resource('accounts', AccountController::class)->except(['show']);
    Route::get('parties/lookup', [PartyController::class, 'lookup'])->name('parties.lookup');
    Route::get('parties/{party}/open-invoices', [PartyController::class, 'openInvoices'])->name('parties.open-invoices');
    Route::resource('parties', PartyController::class);
    Route::post('parties/{party}/prices', [PartyPriceController::class, 'store'])->name('parties.prices.store')->middleware('feature:customer_prices');
    Route::delete('parties/{party}/prices/{price}', [PartyPriceController::class, 'destroy'])->name('parties.prices.destroy')->middleware('feature:customer_prices');

    Route::prefix('vouchers/{voucherType}')->whereIn('voucherType', ['receipt', 'payment', 'journal', 'contra'])
        ->name('vouchers.')->controller(VoucherController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{entry}', 'show')->name('show');
            Route::get('{entry}/edit', 'edit')->name('edit');
            Route::put('{entry}', 'update')->name('update');
            Route::delete('{entry}', 'destroy')->name('destroy');
        });

    // Inventory
    Route::get('products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
    Route::resource('products', ProductController::class);
    Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'create', 'store', 'show', 'destroy'])
        ->middleware('feature:multi_warehouse');

    // Sales, purchases and returns share one controller.
    Route::prefix('{doc}')->whereIn('doc', array_keys(InvoiceController::DOCS))
        ->name('invoices.')->controller(InvoiceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{invoice}', 'show')->name('show')->whereNumber('invoice');
            Route::get('{invoice}/edit', 'edit')->name('edit')->whereNumber('invoice');
            Route::put('{invoice}', 'update')->name('update')->whereNumber('invoice');
            Route::delete('{invoice}', 'destroy')->name('destroy')->whereNumber('invoice');
            Route::get('{invoice}/print', 'print')->name('print')->whereNumber('invoice');
        });

    // Quotations, sales orders, purchase orders
    Route::prefix('{orderDoc}')->whereIn('orderDoc', array_keys(OrderController::DOCS))
        ->name('orders.')->controller(OrderController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{order}', 'show')->name('show')->whereNumber('order');
            Route::get('{order}/edit', 'edit')->name('edit')->whereNumber('order');
            Route::put('{order}', 'update')->name('update')->whereNumber('order');
            Route::delete('{order}', 'destroy')->name('destroy')->whereNumber('order');
            Route::put('{order}/cancel', 'cancel')->name('cancel')->whereNumber('order');
            Route::get('{order}/print', 'print')->name('print')->whereNumber('order');
        });

    // Delivery challans
    Route::middleware('feature:delivery_challans')->group(function () {
        Route::get('deliveries/{delivery}/print', [DeliveryController::class, 'print'])->name('deliveries.print');
        Route::resource('deliveries', DeliveryController::class);
    });

    // Point of sale and register shifts
    Route::middleware('feature:pos')->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('pos');
        Route::post('pos', [PosController::class, 'store'])->name('pos.store');
        Route::get('pos/recent', [PosController::class, 'recent'])->name('pos.recent');
        Route::get('cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
        Route::post('cash-sessions', [CashSessionController::class, 'store'])->name('cash-sessions.store');
        Route::get('cash-sessions/{cashSession}', [CashSessionController::class, 'show'])->name('cash-sessions.show');
        Route::get('cash-sessions/{cashSession}/close', [CashSessionController::class, 'closeForm'])->name('cash-sessions.close');
        Route::put('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close']);
        Route::get('cash-sessions/{cashSession}/print', [CashSessionController::class, 'print'])->name('cash-sessions.print');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('trial-balance', 'trialBalance')->name('trial-balance');
        Route::get('profit-loss', 'profitLoss')->name('profit-loss');
        Route::get('balance-sheet', 'balanceSheet')->name('balance-sheet');
        Route::get('ledger', 'ledger')->name('ledger');
        Route::get('party-statement', 'partyStatement')->name('party-statement');
        Route::get('aging', 'aging')->name('aging');
        Route::get('day-book', 'dayBook')->name('day-book');
        Route::get('cash-book', 'cashBook')->name('cash-book');
        Route::get('stock', 'stock')->name('stock');
        Route::get('stock-movement', 'stockMovement')->name('stock-movement');
        Route::get('low-stock', 'lowStock')->name('low-stock');
        Route::get('register', 'register')->name('register');
        Route::get('product-sales', 'productSales')->name('product-sales');
    });
});
