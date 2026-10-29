<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
