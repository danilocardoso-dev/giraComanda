<?php

namespace App\Actions;

use App\BranchRole;
use App\CompanyRole;
use App\Models\Branch;
use App\Models\BranchMembership;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\EstablishmentSetting;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCompany
{
    /**
     * @param  array{company_name: string, branch_name: string, owner_name: string, owner_email: string, owner_password: string}  $data
     */
    public function handle(array $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            $company = Company::query()->create([
                'name' => $data['company_name'],
                'slug' => $this->uniqueCompanySlug($data['company_name']),
                'customer_sharing_enabled' => false,
                'active' => true,
            ]);
            $branch = Branch::query()->create([
                'company_id' => $company->id,
                'name' => $data['branch_name'],
                'slug' => $this->uniqueBranchSlug($company, $data['branch_name']),
                'active' => true,
            ]);
            $owner = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
                'role' => UserRole::Admin,
                'active' => true,
            ]);

            CompanyMembership::query()->create([
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'role' => CompanyRole::CompanyAdmin,
                'active' => true,
            ]);
            BranchMembership::query()->create([
                'branch_id' => $branch->id,
                'user_id' => $owner->id,
                'role' => BranchRole::Manager,
                'active' => true,
            ]);
            EstablishmentSetting::query()->create([
                'company_id' => $company->id,
                'name' => $company->name,
                'general_due_day' => config('giracomanda.general_due_day'),
                'currency' => 'BRL',
            ]);
            Subscription::query()->create([
                'company_id' => $company->id,
                'status' => SubscriptionStatus::Trial,
                'monthly_amount_cents' => 0,
                'billing_day' => 10,
                'started_at' => today(),
                'trial_ends_at' => today()->addDays(14),
            ]);

            return $company;
        });
    }

    private function uniqueCompanySlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'empresa';
        $slug = $baseSlug;
        $suffix = 2;

        while (Company::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function uniqueBranchSlug(Company $company, string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'matriz';
        $slug = $baseSlug;
        $suffix = 2;

        while ($company->branches()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
