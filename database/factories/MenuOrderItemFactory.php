<?php

namespace Database\Factories;

use App\Models\MenuOrder;
use App\Models\MenuOrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuOrderItem>
 */
class MenuOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_order_id' => MenuOrder::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(2, true),
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'total_cents' => 1000,
        ];
    }
}
