<?php

namespace Database\Factories;

use App\AuditArea;
use App\AuditEvent;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => fn (): ?int => Branch::query()->where('slug', 'matriz')->value('id'),
            'user_id' => User::factory(),
            'event' => AuditEvent::Updated,
            'area' => AuditArea::Products,
            'subject_id' => fake()->numberBetween(1, 1000),
            'subject_label' => fake()->words(2, true),
            'description' => 'Produto atualizado',
            'changed_fields' => ['name'],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
