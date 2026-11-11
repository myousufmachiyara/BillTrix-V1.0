<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('short_name', 12);
            $table->boolean('allow_decimal')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete(); // base (smallest) unit
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku', 50)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('brand', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('purchase_price', 16, 4)->default(0); // last/standard purchase price per base unit
            $table->decimal('sale_price', 16, 4)->default(0);     // default retail price per base unit
            $table->decimal('min_sale_price', 16, 4)->default(0);
            $table->decimal('avg_cost', 16, 4)->default(0);       // moving weighted average cost per base unit
            $table->decimal('reorder_level', 16, 4)->default(0);
            $table->boolean('track_stock')->default(true);        // false for services / non-stock items
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'sku']);
            $table->index(['business_id', 'barcode']);
            $table->index(['business_id', 'name']);
        });

        // Alternate units, e.g. 1 Carton = 24 Pcs. factor = how many base units in this unit.
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('factor', 16, 4);
            $table->string('barcode', 64)->nullable();
            $table->decimal('sale_price', 16, 4)->nullable();      // optional fixed price for this pack
            $table->decimal('purchase_price', 16, 4)->nullable();
            $table->unique(['product_id', 'unit_id']);
            $table->index('barcode');
        });

        // Price per base unit for each price level (Retail, Wholesale, Distributor ...).
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_level_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 16, 4);
            $table->unique(['product_id', 'price_level_id']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 16, 4)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['business_id', 'warehouse_id']);
        });

        // Immutable stock ledger. Quantity is signed and always in base units.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20); // opening | purchase | sale | purchase_return | sale_return | adjustment | transfer_in | transfer_out
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->morphs('source');
            $table->string('reason', 30)->nullable(); // damaged | expired | lost | found | count ...
            $table->timestamps();
            $table->index(['product_id', 'date']);
            $table->index(['business_id', 'date']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->date('date');
            $table->string('type', 20); // opening | adjustment | count
            $table->string('reason', 30)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_value', 16, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 16, 4)->nullable(); // for physical counts
            $table->decimal('quantity', 16, 4); // signed change in base units
            $table->decimal('unit_cost', 16, 4)->default(0);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('number', 30);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('factor', 16, 4)->default(1);
            $table->decimal('quantity', 16, 4);
        });
    }

    public function down(): void
    {
        foreach (['stock_transfer_items', 'stock_transfers', 'stock_adjustment_items', 'stock_adjustments', 'stock_movements', 'stock_balances', 'product_prices', 'product_units', 'products', 'categories', 'units'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
