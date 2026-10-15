<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\BranchRole;
use App\CompanyRole;
use App\SubscriptionStatus;
use App\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->effectiveRole(), $roles, true);
    }

    public function effectiveRole(): UserRole
    {
        if (app()->bound('currentUserRole')) {
            return app('currentUserRole');
        }

        return $this->role;
    }

    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function branchMemberships(): HasMany
    {
        return $this->hasMany(BranchMembership::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_memberships')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_memberships')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    public function platformAdmin(): HasOne
    {
        return $this->hasOne(PlatformAdmin::class);
    }

    /** @return Builder<Branch> */
    public function accessibleBranchesQuery(): Builder
    {
        $adminCompanyIds = $this->companyMemberships()
            ->where('active', true)
            ->where('role', CompanyRole::CompanyAdmin)
            ->pluck('company_id');
        $branchIds = $this->branchMemberships()
            ->where('active', true)
            ->pluck('branch_id');

        return Branch::query()
            ->with('company')
            ->where('active', true)
            ->whereHas('company', fn (Builder $query): Builder => $query
                ->where('active', true)
                ->where(function (Builder $query): void {
                    $query->whereDoesntHave('subscription')
                        ->orWhereHas('subscription', fn (Builder $query): Builder => $query
                            ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value]));
                }))
            ->where(function (Builder $query) use ($adminCompanyIds, $branchIds): void {
                $query->whereIn('company_id', $adminCompanyIds)
                    ->orWhereIn('id', $branchIds);
            });
    }

    public function roleForBranch(Branch $branch): ?UserRole
    {
        $companyRole = $this->companyMemberships()
            ->where('company_id', $branch->company_id)
            ->where('active', true)
            ->value('role');

        if ($companyRole === CompanyRole::CompanyAdmin) {
            return UserRole::Admin;
        }

        $branchRole = $this->branchMemberships()
            ->where('branch_id', $branch->id)
            ->where('active', true)
            ->value('role');

        return match ($branchRole) {
            BranchRole::Manager => UserRole::Admin,
            BranchRole::Cashier => UserRole::Cashier,
            BranchRole::Attendant => UserRole::Attendant,
            default => null,
        };
    }
}
