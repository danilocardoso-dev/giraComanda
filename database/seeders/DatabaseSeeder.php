<?php

namespace Database\Seeders;

use App\BranchRole;
use App\CompanyRole;
use App\Models\Branch;
use App\Models\BranchMembership;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\EstablishmentSetting;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::query()->where('slug', 'empresa-inicial')->firstOrFail();
        $branch = Branch::query()
            ->whereBelongsTo($company)
            ->where('slug', 'matriz')
            ->firstOrFail();

        EstablishmentSetting::query()->firstOrCreate(['company_id' => $company->id], [
            'name' => config('giracomanda.establishment_name'),
            'general_due_day' => config('giracomanda.general_due_day'),
        ]);

        $adminEmail = config('giracomanda.initial_admin.email');
        $adminPassword = config('giracomanda.initial_admin.password');

        if ($adminEmail && $adminPassword) {
            $platformAdmin = User::query()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => config('giracomanda.initial_admin.name'),
                    'password' => $adminPassword,
                    'role' => UserRole::Admin,
                    'active' => true,
                ],
            );
            PlatformAdmin::query()->firstOrCreate(['user_id' => $platformAdmin->id]);
            $platformAdmin->companyMemberships()->delete();
            $platformAdmin->branchMemberships()->delete();
        }

        $ownerEmail = config('giracomanda.initial_establishment_owner.email');
        $ownerPassword = config('giracomanda.initial_establishment_owner.password');

        if ($ownerEmail && $ownerPassword && $ownerEmail !== $adminEmail) {
            $owner = User::query()->updateOrCreate(
                ['email' => $ownerEmail],
                [
                    'name' => config('giracomanda.initial_establishment_owner.name'),
                    'password' => $ownerPassword,
                    'role' => UserRole::Admin,
                    'active' => true,
                ],
            );
            CompanyMembership::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $owner->id],
                ['role' => CompanyRole::CompanyAdmin, 'active' => true],
            );
            BranchMembership::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'user_id' => $owner->id],
                ['role' => BranchRole::Manager, 'active' => true],
            );
        }
    }
}
