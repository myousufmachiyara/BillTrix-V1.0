<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();
            $table->string('voucher_type', 50); // flexible: 'purchase', 'sale', 'payment', 'receipt', 'journal', 'purchase_return', 'sale_return', etc.
            $table->date('voucher_date');
            
            // Reference to source document (polymorphic)
            $table->string('reference_type')->nullable(); // e.g., 'App\Models\PurchaseInvoice'
            $table->unsignedBigInteger('reference_id')->nullable(); // e.g., purchase_invoice.id
            
            // Simple vouchers (single Dr/Cr pair) - for payment/receipt vouchers
            $table->unsignedBigInteger('ac_dr_sid')->nullable(); 
            $table->unsignedBigInteger('ac_cr_sid')->nullable();
            $table->decimal('amount', 15, 2)->nullable(); // amount for simple vouchers
            
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('ac_dr_sid')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('ac_cr_sid')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            
            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('voucher_date');
            $table->index('voucher_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
