<?php

namespace Database\Factories;

use App\BranchRole;
use App\CompanyRole;
use App\Models\Branch;
use App\Models\BranchMembership;
use App\Models\CompanyMembership;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $branch = Branch::query()
                ->with('company')
                ->where('slug', 'matriz')
                ->first();

            if ($branch === null) {
                return;
            }

            CompanyMembership::query()->firstOrCreate(
                ['company_id' => $branch->company_id, 'user_id' => $user->id],
                [
                    'role' => $user->role === UserRole::Admin ? CompanyRole::CompanyAdmin : CompanyRole::Member,
                    'active' => $user->active,
                ],
            );

            BranchMembership::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'user_id' => $user->id],
                [
                    'role' => match ($user->role) {
                        UserRole::Admin => BranchRole::Manager,
                        UserRole::Cashier => BranchRole::Cashier,
                        UserRole::Attendant => BranchRole::Attendant,
                    },
                    'active' => $user->active,
                ],
            );
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Attendant,
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->admin()->afterCreating(function (User $user): void {
            PlatformAdmin::query()->firstOrCreate(['user_id' => $user->id]);
        });
    }

    public function cashier(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Cashier,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }
}
