<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** People who opened the public demo. Not tied to a business: the demo install's owner follows them up. */
    public function up(): void
    {
        Schema::create('demo_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 20)->unique();
            $table->string('business_name', 120)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('business_type', 60)->nullable();
            $table->string('source', 80)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedInteger('visits')->default(1);
            $table->string('status', 20)->default('new')->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_leads');
    }
};
