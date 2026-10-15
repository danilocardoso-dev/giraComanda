<?php

namespace Database\Factories;

use App\BranchRole;
use App\Models\Branch;
use App\Models\BranchMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchMembership>
 */
class BranchMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'role' => BranchRole::Attendant,
            'active' => true,
        ];
    }
}
