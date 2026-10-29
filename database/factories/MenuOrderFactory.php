<?php

namespace Database\Factories;

use App\MenuOrderStatus;
use App\Models\Branch;
use App\Models\Card;
use App\Models\MenuOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuOrder>
 */
class MenuOrderFactory extends Factory
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
            'card_id' => Card::factory(),
            'tab_id' => null,
            'reviewed_by' => null,
            'status' => MenuOrderStatus::Pending,
            'customer_name' => fake()->firstName(),
            'notes' => null,
            'total_cents' => 0,
            'rejection_reason' => null,
            'reviewed_at' => null,
        ];
    }
}
