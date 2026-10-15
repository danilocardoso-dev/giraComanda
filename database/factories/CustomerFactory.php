<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => fn (): ?int => Branch::query()->where('slug', 'matriz')->value('company_id'),
            'branch_id' => fn (): ?int => Branch::query()->where('slug', 'matriz')->value('id'),
            'name' => fake()->name(),
            'cpf' => $this->validCpf(),
            'phone' => fake()->numerify('###########'),
            'email' => fake()->unique()->safeEmail(),
            'notes' => null,
            'credit_limit_cents' => null,
            'due_day' => null,
            'active' => true,
        ];
    }

    private function validCpf(): string
    {
        $digits = array_map('intval', str_split(fake()->unique()->numerify('#########')));

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += $digits[$index] * (($position + 1) - $index);
            }

            $remainder = ($sum * 10) % 11;
            $digits[] = $remainder === 10 ? 0 : $remainder;
        }

        return implode('', $digits);
    }
}
