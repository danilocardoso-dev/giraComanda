<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Subscription;
use App\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'status' => SubscriptionStatus::Active,
            'monthly_amount_cents' => 19990,
            'billing_day' => 10,
            'billing_phone' => null,
            'started_at' => today(),
            'trial_ends_at' => null,
            'notes' => null,
        ];
    }
}
