<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EstablishmentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstablishmentSetting>
 */
class EstablishmentSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => fn (): ?int => Company::query()->where('slug', 'empresa-inicial')->value('id'),
            'name' => 'GiraComanda',
            'logo_path' => null,
            'document' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'general_due_day' => 10,
            'currency' => 'BRL',
        ];
    }
}
