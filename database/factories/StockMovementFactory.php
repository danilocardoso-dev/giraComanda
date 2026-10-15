<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => fn (): ?int => Branch::query()->where('slug', 'matriz')->value('id'),
            'product_id' => Product::factory(),
            'recorded_by' => User::factory(),
            'tab_id' => null,
            'type' => StockMovementType::Entry,
            'quantity_delta_milliunits' => 1000,
            'balance_after_milliunits' => 1000,
            'description' => fake()->sentence(3),
            'occurred_at' => now(),
        ];
    }
}
