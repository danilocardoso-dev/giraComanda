<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Card;
use App\Models\Tab;
use App\Models\User;
use App\TabStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tab>
 */
class TabFactory extends Factory
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
            'opened_by' => User::factory(),
            'closed_by' => null,
            'cancelled_by' => null,
            'customer_id' => null,
            'status' => TabStatus::Open,
            'total_cents' => 0,
            'opened_at' => now(),
            'closed_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }
}
