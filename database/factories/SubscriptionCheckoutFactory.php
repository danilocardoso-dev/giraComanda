<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionCheckout>
 */
class SubscriptionCheckoutFactory extends Factory
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
            'subscription_invoice_id' => fn (array $attributes) => SubscriptionInvoice::factory()->create(['subscription_id' => $attributes['subscription_id']])->id,
            'uuid' => (string) Str::uuid(),
            'environment' => 'test', 'kind' => 'pix', 'status' => 'pending',
            'remote_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'amount_cents' => 19700,
            'pix_code' => '000201-pix-test', 'expires_at' => now()->addDay(),
        ];
    }
}
