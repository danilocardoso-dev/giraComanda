<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPhoto>
 */
class ProductPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'product-photos/'.fake()->uuid().'.jpg',
            'position' => 0,
        ];
    }
}
