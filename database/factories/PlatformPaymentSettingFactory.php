<?php

namespace Database\Factories;

use App\Models\PlatformPaymentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPaymentSetting>
 */
class PlatformPaymentSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => PlatformPaymentSetting::PROVIDER,
            'environment' => 'test',
        ];
    }
}
