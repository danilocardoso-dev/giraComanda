<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\MonthlyStatementDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyStatementDelivery>
 */
class MonthlyStatementDeliveryFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'statement_month' => now()->startOfMonth(),
            'sent_by' => User::factory(),
            'sent_at' => now(),
        ];
    }
}
