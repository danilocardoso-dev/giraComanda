<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Receivable;
use App\Models\Tab;
use App\ReceivableStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 */
class ReceivableFactory extends Factory
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
            'tab_id' => Tab::factory(),
            'amount_cents' => 1000,
            'paid_cents' => 0,
            'due_date' => now()->addMonth()->startOfDay(),
            'status' => ReceivableStatus::Open,
            'paid_at' => null,
        ];
    }
}
