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
        Schema::create('platform_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->unique();
            $table->string('environment', 20)->default('test');
            $table->text('test_public_key')->nullable();
            $table->text('test_access_token')->nullable();
            $table->text('test_webhook_secret')->nullable();
            $table->string('test_plan_id', 100)->nullable();
            $table->text('production_public_key')->nullable();
            $table->text('production_access_token')->nullable();
            $table->text('production_webhook_secret')->nullable();
            $table->string('production_plan_id', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_payment_settings');
    }
};
