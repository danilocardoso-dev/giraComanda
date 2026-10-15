<?php

namespace Database\Factories;

use App\CashSessionStatus;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
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
            'opened_by' => User::factory(),
            'closed_by' => null,
            'status' => CashSessionStatus::Open,
            'opening_amount_cents' => 10000,
            'expected_amount_cents' => null,
            'counted_amount_cents' => null,
            'difference_cents' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'closed_by' => User::factory(),
            'status' => CashSessionStatus::Closed,
            'expected_amount_cents' => 10000,
            'counted_amount_cents' => 10000,
            'difference_cents' => 0,
            'closed_at' => now(),
        ]);
    }
}
