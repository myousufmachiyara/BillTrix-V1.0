<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts: a tree. Group accounts hold children; only leaf accounts take postings.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('type', 12);            // asset | liability | equity | income | expense
            $table->string('subtype', 20)->nullable(); // cash | bank | receivable | payable | inventory | tax ...
            $table->string('system_key', 40)->nullable(); // stable key used by posting rules
            $table->boolean('is_group')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'system_key']);
            $table->index(['business_id', 'type']);
        });

        Schema::create('price_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Customers and suppliers. Their balances live in the AR / AP control accounts, tagged by party_id.
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // customer | supplier | both
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('ntn', 30)->nullable();
            $table->string('cnic', 20)->nullable();
            $table->decimal('credit_limit', 16, 2)->default(0); // 0 = no limit
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->foreignId('price_level_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_walk_in')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'name']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->string('type', 20); // journal | receipt | payment | contra | opening | invoice | stock
            $table->date('date');
            $table->nullableMorphs('source'); // invoice, stock adjustment etc.
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('narration')->nullable();
            $table->decimal('amount', 16, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'date']);
            $table->index(['business_id', 'type', 'date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->string('memo')->nullable();
            $table->index(['account_id', 'date']);
            $table->index(['party_id', 'date']);
            $table->index(['business_id', 'date']);
        });

        // Links a receipt/payment entry to the invoice(s) it settles.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id'); // FK added after invoices table exists
            $table->decimal('amount', 16, 2);
            $table->timestamps();
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        foreach (['payment_allocations', 'journal_lines', 'journal_entries', 'parties', 'price_levels', 'accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
