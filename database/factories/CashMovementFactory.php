<?php

namespace Database\Factories;

use App\CashMovementType;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
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
            'cash_session_id' => CashSession::factory(),
            'recorded_by' => User::factory(),
            'type' => CashMovementType::Supply,
            'amount_cents' => 1000,
            'description' => fake()->sentence(3),
            'occurred_at' => now(),
        ];
    }
}
