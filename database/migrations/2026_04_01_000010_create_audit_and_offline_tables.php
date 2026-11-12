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
        // Who changed what, and when.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 20);               // created | updated | deleted | login | login_failed | logout | exported
            $table->nullableMorphs('auditable');
            $table->string('label')->nullable();       // human reference, e.g. "SI-000123" or a product name
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
        });

        // Withholding tax on receipts and payments.
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->decimal('wht_rate', 6, 3)->default(0)->after('amount');
            $table->decimal('wht_amount', 16, 2)->default(0)->after('wht_rate');
        });

        // Offline POS: the browser's id for a bill, so a re-sent bill is never saved twice.
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('number');
            $table->unique(['business_id', 'client_uuid']);
        });

        BusinessProvisioner::syncPermissions();
        Business::query()->each(fn (Business $b) => app(BusinessProvisioner::class)->upgrade($b));
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
        Schema::table('journal_entries', fn (Blueprint $t) => $t->dropColumn(['wht_rate', 'wht_amount']));
        Schema::dropIfExists('audit_logs');
    }
};
