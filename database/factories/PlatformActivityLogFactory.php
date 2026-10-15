<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PlatformActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformActivityLog>
 */
class PlatformActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_admin_user_id' => User::factory()->platformAdmin(),
            'company_id' => Company::factory(),
            'target_user_id' => null,
            'action' => 'subscription_updated',
            'description' => fake()->sentence(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
