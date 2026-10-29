<?php

namespace Database\Factories;

use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionGatewayPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionGatewayPayment>
 */
class SubscriptionGatewayPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_checkout_id' => SubscriptionCheckout::factory(),
            'environment' => 'test', 'remote_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'status' => 'pending', 'amount_cents' => 29700,
        ];
    }
}
