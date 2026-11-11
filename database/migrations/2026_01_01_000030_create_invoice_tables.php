<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One table for sale, purchase, sale_return and purchase_return documents.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // sale | purchase | sale_return | purchase_return
            $table->string('number', 30);
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_of_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('payment_mode', 10)->default('credit'); // cash | credit
            $table->string('reference')->nullable();       // supplier bill no., customer PO no.
            $table->string('customer_name')->nullable();   // walk-in name on receipt
            $table->string('customer_phone', 30)->nullable();
            $table->decimal('subtotal', 16, 2)->default(0);       // sum of line amounts before line discount
            $table->decimal('item_discount', 16, 2)->default(0);  // sum of line discounts
            $table->decimal('discount', 16, 2)->default(0);       // invoice-level discount
            $table->decimal('tax_total', 16, 2)->default(0);
            $table->decimal('other_charges', 16, 2)->default(0); // freight / loading
            $table->decimal('round_off', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->decimal('cost_total', 16, 2)->default(0); // COGS for sales, for profit reports
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'type', 'date']);
            $table->index(['party_id', 'type']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_item_id')->nullable(); // original invoice line for returns
            $table->decimal('factor', 16, 4)->default(1);
            $table->decimal('quantity', 16, 4);
            $table->decimal('base_quantity', 16, 4);
            $table->decimal('price', 16, 4);
            $table->decimal('discount_percent', 6, 3)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('amount', 16, 2); // net line amount incl. tax
            $table->decimal('unit_cost', 16, 4)->default(0); // cost per base unit at posting
            $table->index('product_id');
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', fn (Blueprint $t) => $t->dropForeign(['invoice_id']));
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
