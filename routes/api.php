<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\PartyController;
use Illuminate\Support\Facades\Route;

/*
| BillTrix mobile API v1 — see docs/API.md.
| Authenticate with POST /api/v1/auth/login, then send "Authorization: Bearer <token>".
*/

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'tenant', 'throttle:120,1'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [DocumentController::class, 'dashboard']);
        Route::get('lookups', [CatalogController::class, 'lookups']);
        Route::get('products', [CatalogController::class, 'products']);
        Route::get('products/{product}', [CatalogController::class, 'product']);

        Route::get('parties', [PartyController::class, 'index']);
        Route::post('parties', [PartyController::class, 'store']);
        Route::get('parties/{party}', [PartyController::class, 'show']);
        Route::get('parties/{party}/statement', [PartyController::class, 'statement']);

        Route::get('invoices', [DocumentController::class, 'invoices']);
        Route::post('invoices', [DocumentController::class, 'storeInvoice']);
        Route::get('invoices/{invoice}', [DocumentController::class, 'invoice']);
        Route::get('orders', [DocumentController::class, 'orders']);
        Route::post('orders', [DocumentController::class, 'storeOrder']);
        Route::get('orders/{order}', [DocumentController::class, 'order']);
        Route::post('receipts', [DocumentController::class, 'storeReceipt']);
    });
});
