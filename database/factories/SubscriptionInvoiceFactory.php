<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'reference_month' => today()->startOfMonth(),
            'due_date' => today()->startOfMonth()->addDays(9),
            'amount_cents' => 19990,
            'paid_at' => null,
            'payment_method' => null,
            'notes' => null,
            'generated_automatically' => false,
        ];
    }
}
