<?php

namespace Database\Factories;

use App\CardStatus;
use App\Models\Branch;
use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
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
            'code' => fake()->unique()->numerify('CT-###'),
            'status' => CardStatus::Available,
            'notes' => null,
        ];
    }
}
