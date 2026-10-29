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
        Schema::create('menu_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->foreignId('tab_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('customer_name', 80)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('total_cents');
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['card_id', 'created_at']);
        });

        Schema::create('menu_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_order_items');
        Schema::dropIfExists('menu_orders');
    }
};
