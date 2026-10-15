<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branch_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_cents');
            $table->unsignedBigInteger('stock_milliunits')->nullable();
            $table->unsignedBigInteger('min_stock_milliunits')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->index(['branch_id', 'active']);
        });

        $now = now();

        DB::table('products')->orderBy('id')->each(function (object $product) use ($now): void {
            $branchId = DB::table('branches')
                ->where('company_id', $product->company_id)
                ->orderBy('id')
                ->value('id');

            if ($branchId === null) {
                return;
            }

            DB::table('branch_products')->insert([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'price_cents' => $product->price_cents,
                'stock_milliunits' => $product->stock_milliunits,
                'min_stock_milliunits' => $product->min_stock_milliunits,
                'active' => (bool) $product->active,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_products');
    }
};
