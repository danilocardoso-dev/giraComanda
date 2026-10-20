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
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->boolean('enabled')->default(false);
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('paid_until')->nullable();
        });
        Schema::create('subscription_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_invoice_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('environment', 20);
            $table->string('kind', 10);
            $table->string('status', 30)->default('creating');
            $table->string('remote_id', 100)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->text('payer_email')->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('pix_code')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('reconciliation_offset')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['environment', 'kind', 'remote_id'], 'checkout_remote_unique');
            $table->index(['subscription_id', 'kind', 'status']);
        });
        Schema::create('subscription_gateway_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_checkout_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('environment', 20);
            $table->string('remote_id', 100);
            $table->string('status', 30);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->string('review_reason', 100)->nullable();
            $table->timestamps();
            $table->unique(['environment', 'remote_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_gateway_payments');
        Schema::dropIfExists('subscription_checkouts');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('paid_until'));
        Schema::table('platform_payment_settings', fn (Blueprint $table) => $table->dropColumn('enabled'));
    }
};
