<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\ProductSaleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabItem>
 */
class TabItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tab_id' => Tab::factory(),
            'product_id' => Product::factory(),
            'added_by' => User::factory(),
            'product_name' => 'Coca-Cola',
            'sale_type' => ProductSaleType::Unit,
            'quantity_milliunits' => 1000,
            'unit_price_cents' => 1000,
            'total_cents' => 1000,
        ];
    }
}
