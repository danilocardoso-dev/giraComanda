<?php

namespace Tests\Feature;

use App\CompanyRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BranchContextTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_single_accessible_branch_is_selected_automatically(): void
    {
        $user = User::factory()->create();
        $branch = Branch::query()->where('slug', 'matriz')->sole();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSessionHas('active_branch_id', $branch->id);
        $response->assertSee($branch->name);
    }

    public function test_user_with_multiple_branches_must_choose_one(): void
    {
        $user = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $secondBranch = Branch::factory()->for($company)->create(['name' => 'Centro']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('branches.index'));

        $selection = $this->get(route('branches.index'));
        $selection->assertOk();
        $selection->assertSee('Matriz');
        $selection->assertSee($secondBranch->name);
    }

    public function test_company_admin_can_select_any_branch_from_the_company(): void
    {
        $user = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $branch = Branch::factory()->for($company)->create(['name' => 'Bairro']);

        $response = $this->actingAs($user)->post(route('branches.activate', $branch));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('active_branch_id', $branch->id);
    }

    public function test_user_cannot_select_a_branch_from_another_company(): void
    {
        $user = User::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();

        $response = $this->actingAs($user)->post(route('branches.activate', $otherBranch));

        $response->assertNotFound();
    }

    public function test_company_membership_grants_access_across_companies(): void
    {
        $user = User::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create(['name' => 'Outra unidade']);
        CompanyMembership::factory()->for($otherCompany)->for($user)->create([
            'role' => CompanyRole::CompanyAdmin,
        ]);

        $response = $this->actingAs($user)->post(route('branches.activate', $otherBranch));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('active_branch_id', $otherBranch->id);
    }
}
