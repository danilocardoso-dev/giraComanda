<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformDashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('platform.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_company_admin_cannot_view_the_platform_dashboard(): void
    {
        $companyAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($companyAdmin)->get(route('platform.dashboard'));

        $response->assertForbidden();
    }

    public function test_platform_admin_without_company_access_views_the_platform_dashboard(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $platformAdmin->companyMemberships()->delete();
        $platformAdmin->branchMemberships()->delete();
        Company::factory()->create(['name' => 'Segundo Restaurante']);

        $response = $this->actingAs($platformAdmin)->get(route('platform.dashboard'));

        $response->assertOk();
        $response->assertSee('Administração da plataforma');
        $response->assertSee('Sua plataforma em números');
        $response->assertViewHas('companiesCount', 2);
    }

    public function test_platform_admin_is_redirected_away_from_establishment_operation(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get(route('dashboard'));

        $response->assertRedirect(route('platform.dashboard'));
    }
}
