<?php

use App\Models\Business;
use App\Services\BusinessProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quotations, sales orders and purchase orders. They never move stock or post to the ledger.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // quotation | sale_order | purchase_order
            $table->string('number', 30);
            $table->date('date');
            $table->date('due_date')->nullable(); // valid until / expected delivery
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 15)->default('open'); // open | partial | completed | cancelled | converted
            $table->string('reference')->nullable();
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('item_discount', 16, 2)->default(0);
            $table->decimal('discount', 16, 2)->default(0);
            $table->decimal('tax_total', 16, 2)->default(0);
            $table->decimal('other_charges', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'type', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('factor', 16, 4)->default(1);
            $table->decimal('quantity', 16, 4);
            $table->decimal('base_quantity', 16, 4);
            $table->decimal('fulfilled_quantity', 16, 4)->default(0); // base units delivered / received / invoiced
            $table->decimal('price', 16, 4);
            $table->decimal('discount_percent', 6, 3)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('amount', 16, 2);
        });

        // Delivery challans: goods leave the warehouse before (or without yet) being invoiced.
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->date('date');
            $table->string('vehicle_no', 30)->nullable();
            $table->string('driver', 80)->nullable();
            $table->string('ship_to')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('cost_total', 16, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
            $table->index(['party_id', 'invoice_id']);
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('factor', 16, 4)->default(1);
            $table->decimal('quantity', 16, 4);
            $table->decimal('base_quantity', 16, 4);
            $table->decimal('unit_cost', 16, 4)->default(0);
        });

        // A cashier's register shift: opening float → sales → counted cash at close.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete(); // the till's cash account
            $table->string('number', 30);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 16, 2)->default(0);
            $table->decimal('expected_cash', 16, 2)->nullable();
            $table->decimal('counted_cash', 16, 2)->nullable();
            $table->decimal('difference', 16, 2)->nullable();
            $table->json('denominations')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
            $table->index(['user_id', 'closed_at']);
        });

        // Special price for one customer (per base unit).
        Schema::create('party_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 16, 4);
            $table->timestamps();
            $table->unique(['party_id', 'product_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('return_of_id')->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->after('source_item_id')->constrained()->nullOnDelete();
            $table->foreignId('delivery_item_id')->nullable()->after('order_item_id')->constrained()->nullOnDelete();
        });

        // Bring existing businesses up to date: new system accounts and permissions.
        BusinessProvisioner::syncPermissions();
        Business::query()->each(fn (Business $b) => app(BusinessProvisioner::class)->upgrade($b));
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_item_id');
            $table->dropConstrainedForeignId('order_item_id');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
            $table->dropConstrainedForeignId('order_id');
        });
        foreach (['party_prices', 'cash_sessions', 'delivery_items', 'deliveries', 'order_items', 'orders'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
