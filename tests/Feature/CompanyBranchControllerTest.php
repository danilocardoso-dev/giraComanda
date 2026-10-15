<?php

namespace Tests\Feature;

use App\BranchRole;
use App\CompanyRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CompanyBranchControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_company_admin_can_view_and_create_a_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();

        $index = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $currentBranch->id])
            ->get(route('company-branches.index'));
        $response = $this->post(route('company-branches.store'), ['name' => 'Unidade Centro']);

        $index->assertOk()->assertSee('Matriz')->assertSee('Compartilhamento de clientes');
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Filial Unidade Centro cadastrada com sucesso.');
        $this->assertDatabaseHas('branches', [
            'company_id' => $currentBranch->company_id,
            'name' => 'Unidade Centro',
            'slug' => 'unidade-centro',
            'active' => true,
        ]);
    }

    public function test_branch_manager_cannot_manage_the_company_branches(): void
    {
        $manager = User::factory()->admin()->create();
        $branch = Branch::query()->where('slug', 'matriz')->sole();
        $manager->companyMemberships()->update(['role' => CompanyRole::Member]);
        $manager->branchMemberships()->update(['role' => BranchRole::Manager]);

        $response = $this->actingAs($manager)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('company-branches.index'));

        $response->assertForbidden();
    }

    public function test_company_admin_cannot_update_a_branch_from_another_company(): void
    {
        $admin = User::factory()->admin()->create();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $currentBranch->id])
            ->put(route('company-branches.update', $otherBranch), [
                'name' => 'Tentativa indevida',
                'active' => '1',
            ]);

        $response->assertNotFound();
        $this->assertNotSame('Tentativa indevida', $otherBranch->fresh()->name);
    }

    public function test_current_branch_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::query()->where('slug', 'matriz')->sole();

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->put(route('company-branches.update', $branch), [
                'name' => $branch->name,
                'active' => '0',
            ]);

        $response->assertSessionHasErrors([
            'active' => 'Troque de filial antes de desativar a filial atualmente em uso.',
        ]);
        $this->assertTrue($branch->fresh()->active);
    }

    public function test_company_admin_can_enable_customer_sharing(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::query()->where('slug', 'matriz')->sole();

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->put(route('company-customer-sharing.update'), [
                'customer_sharing_enabled' => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Compartilhamento de clientes ativado para todas as filiais.');
        $this->assertTrue($branch->company()->sole()->customer_sharing_enabled);
    }
}
