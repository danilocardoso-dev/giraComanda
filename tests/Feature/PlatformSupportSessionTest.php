<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformSupportSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_admin_can_enter_and_leave_support_mode(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create(['name' => 'Restaurante Suporte']);
        $branch = Branch::factory()->for($company)->create(['name' => 'Matriz Suporte']);
        Subscription::factory()->for($company)->create(['status' => SubscriptionStatus::Suspended]);

        $startResponse = $this->actingAs($platformAdmin)->post(route('platform.support.store', $company), [
            'branch_id' => $branch->id,
        ]);

        $startResponse->assertRedirect(route('dashboard'));
        $startResponse->assertSessionHas('support_company_id', $company->id);
        $startResponse->assertSessionHas('support_branch_id', $branch->id);

        $dashboardResponse = $this->actingAs($platformAdmin)
            ->withSession(['support_company_id' => $company->id, 'support_branch_id' => $branch->id])
            ->get(route('dashboard'));

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Modo suporte ativo');
        $dashboardResponse->assertSee('Restaurante Suporte');

        $companyAdminResponse = $this->actingAs($platformAdmin)
            ->withSession(['support_company_id' => $company->id, 'support_branch_id' => $branch->id])
            ->get(route('company-branches.index'));
        $companyAdminResponse->assertOk();

        $endResponse = $this->actingAs($platformAdmin)
            ->withSession(['support_company_id' => $company->id, 'support_branch_id' => $branch->id])
            ->delete(route('platform.support.destroy'));

        $endResponse->assertRedirect(route('platform.companies.show', $company));
        $endResponse->assertSessionMissing('support_company_id');
        $this->assertDatabaseHas('platform_activity_logs', ['company_id' => $company->id, 'action' => 'support_ended']);
    }

    public function test_support_cannot_start_with_branch_from_another_company(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create();
        Subscription::factory()->for($company)->create();
        $otherBranch = Branch::factory()->create();

        $response = $this->actingAs($platformAdmin)->post(route('platform.support.store', $company), [
            'branch_id' => $otherBranch->id,
        ]);

        $response->assertNotFound();
    }

    public function test_regular_company_admin_cannot_start_support_mode(): void
    {
        $companyAdmin = User::factory()->admin()->create();
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        Subscription::factory()->for($company)->create();

        $response = $this->actingAs($companyAdmin)->post(route('platform.support.store', $company), [
            'branch_id' => $branch->id,
        ]);

        $response->assertForbidden();
    }
}
