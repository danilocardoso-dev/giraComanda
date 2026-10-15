<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receivable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'receivable_id' => Receivable::factory(),
            'amount_cents' => 1000,
        ];
    }
}
