<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Company;
use App\Models\Product;
use App\ProductSaleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $branchId = Branch::query()->where('slug', 'matriz')->value('id');

            if ($branchId === null) {
                return;
            }

            BranchProduct::query()->firstOrCreate(
                ['branch_id' => $branchId, 'product_id' => $product->id],
                [
                    'price_cents' => $product->getRawOriginal('price_cents'),
                    'stock_milliunits' => $product->getRawOriginal('stock_milliunits'),
                    'min_stock_milliunits' => $product->getRawOriginal('min_stock_milliunits'),
                    'active' => $product->getRawOriginal('active'),
                ],
            );
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => fn (): ?int => Company::query()->where('slug', 'empresa-inicial')->value('id'),
            'name' => fake()->unique()->words(2, true),
            'sale_type' => ProductSaleType::Unit,
            'price_cents' => 1000,
            'stock_milliunits' => null,
            'min_stock_milliunits' => null,
            'active' => true,
        ];
    }

    public function byWeight(): static
    {
        return $this->state(fn (): array => [
            'sale_type' => ProductSaleType::Weight,
            'price_cents' => 4990,
        ]);
    }
}
