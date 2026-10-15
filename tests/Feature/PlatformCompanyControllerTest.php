<?php

namespace Tests\Feature;

use App\BranchRole;
use App\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\SubscriptionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformCompanyControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_admin_can_view_establishments(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get(route('platform.companies.index'));

        $response->assertOk();
        $response->assertSee('Empresas clientes');
        $response->assertSee('GiraComanda');
    }

    public function test_company_admin_cannot_view_platform_establishments(): void
    {
        $companyAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($companyAdmin)->get(route('platform.companies.index'));

        $response->assertForbidden();
    }

    public function test_platform_admin_can_create_an_establishment_with_its_owner_and_first_branch(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->post(route('platform.companies.store'), [
            'company_name' => 'Restaurante do Centro',
            'branch_name' => 'Matriz',
            'owner_name' => 'Maria Proprietária',
            'owner_email' => 'maria@restaurante.local',
            'owner_password' => 'senha-segura',
            'owner_password_confirmation' => 'senha-segura',
        ]);

        $response->assertRedirect(route('platform.companies.index'));
        $response->assertSessionHas('success', 'Estabelecimento Restaurante do Centro criado com sucesso.');
        $company = Company::query()->where('slug', 'restaurante-do-centro')->sole();
        $branch = $company->branches()->sole();
        $owner = User::query()->where('email', 'maria@restaurante.local')->sole();
        $this->assertSame('Matriz', $branch->name);
        $this->assertTrue(Hash::check('senha-segura', $owner->password));
        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => CompanyRole::CompanyAdmin->value,
            'active' => true,
        ]);
        $this->assertDatabaseHas('branch_memberships', [
            'branch_id' => $branch->id,
            'user_id' => $owner->id,
            'role' => BranchRole::Manager->value,
            'active' => true,
        ]);
        $this->assertDatabaseHas('establishment_settings', [
            'company_id' => $company->id,
            'name' => 'Restaurante do Centro',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'status' => SubscriptionStatus::Trial->value,
        ]);
    }

    public function test_duplicate_owner_email_is_rejected(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $existingUser = User::factory()->create(['email' => 'existente@restaurante.local']);

        $response = $this->actingAs($platformAdmin)
            ->from(route('platform.companies.index'))
            ->post(route('platform.companies.store'), [
                'company_name' => 'Restaurante Duplicado',
                'branch_name' => 'Matriz',
                'owner_name' => 'Usuário Existente',
                'owner_email' => $existingUser->email,
                'owner_password' => 'senha-segura',
                'owner_password_confirmation' => 'senha-segura',
            ]);

        $response->assertRedirect(route('platform.companies.index'));
        $response->assertSessionHasErrors([
            'owner_email' => 'Este e-mail já está cadastrado no sistema.',
        ]);
        $this->assertDatabaseMissing('companies', ['name' => 'Restaurante Duplicado']);
    }

    public function test_platform_admin_can_disable_an_establishment(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();

        $response = $this->actingAs($platformAdmin)->patch(route('platform.companies.update', $company), [
            'active' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', "Estabelecimento {$company->name} desativado.");
        $this->assertFalse($company->refresh()->active);
    }
}
