<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Tab;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'tab_id' => Tab::factory(),
            'customer_id' => null,
            'cash_session_id' => null,
            'recorded_by' => User::factory(),
            'method' => PaymentMethod::Pix,
            'amount_cents' => 1000,
            'paid_at' => now(),
        ];
    }
}
