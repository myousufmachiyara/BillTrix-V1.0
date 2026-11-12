<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product variations (size, colour, …): attributes with values, and one
 * variant per combination with its own SKU, barcode, prices, cost and stock.
 */
return new class extends Migration
{
    private const ITEM_TABLES = ['invoice_items', 'order_items', 'delivery_items', 'stock_adjustment_items', 'stock_transfer_items'];

    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'name']);
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 60);
            $table->string('code', 12)->nullable(); // short code for SKUs, e.g. M, RED
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false)->after('track_batches');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);              // "Red / M"
            $table->string('sku', 80);
            $table->string('barcode', 64)->nullable();
            $table->decimal('sale_price', 16, 4)->nullable();      // blank = product price
            $table->decimal('purchase_price', 16, 4)->nullable();
            $table->decimal('avg_cost', 16, 4)->default(0);
            $table->decimal('reorder_level', 16, 4)->nullable();   // blank = product reorder level
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('attribute_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->restrictOnDelete();
            $table->primary(['product_variant_id', 'attribute_value_id']);
        });

        // Balances are kept per product + variant (0 = no variant) + warehouse.
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id')->default(0)->after('product_id');
        });
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->unique(['product_id', 'variant_id', 'warehouse_id'], 'stock_balances_item_wh_unique');
        });
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'warehouse_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->restrictOnDelete();
        });

        foreach (self::ITEM_TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->restrictOnDelete();
            });
        }

        \App\Services\BusinessProvisioner::syncPermissions();
        foreach (\App\Models\Business::all() as $business) {
            app(\App\Services\BusinessProvisioner::class)->upgrade($business);
        }
    }

    public function down(): void
    {
        foreach ([...self::ITEM_TABLES, 'stock_movements'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropConstrainedForeignId('variant_id'));
        }
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->unique(['product_id', 'warehouse_id']);
        });
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropUnique('stock_balances_item_wh_unique');
            $table->dropColumn('variant_id');
        });
        Schema::dropIfExists('attribute_value_product_variant');
        Schema::dropIfExists('product_variants');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('has_variants'));
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
