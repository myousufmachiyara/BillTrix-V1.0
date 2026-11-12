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
        // Batch & expiry tracking.
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 50);
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'batch_no']);
            $table->index(['business_id', 'expiry_date']);
        });

        Schema::create('batch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 16, 4)->default(0);
            $table->unique(['batch_id', 'warehouse_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('warehouse_id')->constrained()->nullOnDelete();
        });

        foreach (['invoice_items', 'stock_adjustment_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('batch_no', 50)->nullable();
                $table->date('expiry_date')->nullable();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_batches')->default(false)->after('track_stock');
            $table->string('hs_code', 20)->nullable()->after('barcode'); // PCT / HS code reported to FBR
        });

        // Withholding tax and FBR.
        Schema::table('parties', function (Blueprint $table) {
            $table->string('province', 40)->nullable()->after('city');
            $table->boolean('is_filer')->default(true)->after('cnic');   // on FBR's Active Taxpayer List
            $table->boolean('wht_exempt')->default(false)->after('is_filer');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('province', 40)->nullable()->after('address');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('wht_rate', 6, 3)->default(0)->after('round_off');
            $table->decimal('wht_amount', 16, 2)->default(0)->after('wht_rate');
            $table->decimal('service_fee', 16, 2)->default(0)->after('wht_amount'); // FBR POS fee
            $table->string('fbr_status', 12)->nullable()->after('notes');           // pending | synced | failed | skipped
            $table->string('fbr_invoice_number', 80)->nullable()->after('fbr_status');
            $table->unsignedSmallInteger('fbr_attempts')->default(0)->after('fbr_invoice_number');
            $table->text('fbr_response')->nullable()->after('fbr_attempts');
            $table->longText('fbr_qr')->nullable()->after('fbr_response');
            $table->timestamp('fbr_synced_at')->nullable()->after('fbr_qr');
            $table->index(['business_id', 'fbr_status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('phone');
        });

        // New system accounts and permissions for businesses created before this release.
        BusinessProvisioner::syncPermissions();
        Business::query()->each(fn (Business $b) => app(BusinessProvisioner::class)->upgrade($b));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale'));
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'fbr_status']);
            $t->dropColumn(['wht_rate', 'wht_amount', 'service_fee', 'fbr_status', 'fbr_invoice_number', 'fbr_attempts', 'fbr_response', 'fbr_qr', 'fbr_synced_at']);
        });
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn('province'));
        Schema::table('parties', fn (Blueprint $t) => $t->dropColumn(['province', 'is_filer', 'wht_exempt']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['track_batches', 'hs_code']));
        foreach (['stock_adjustment_items', 'invoice_items'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn(['batch_no', 'expiry_date']));
        }
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropConstrainedForeignId('batch_id'));
        Schema::dropIfExists('batch_stocks');
        Schema::dropIfExists('batches');
    }
};
