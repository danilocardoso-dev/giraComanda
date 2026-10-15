<?php

namespace Tests\Feature;

use App\CompanyRole;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformOwnerAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_admin_can_set_temporary_owner_password(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create();
        $owner = User::factory()->admin()->create();
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => CompanyRole::CompanyAdmin,
            'active' => true,
        ]);

        $response = $this->actingAs($platformAdmin)->put(route('platform.owners.password.update', [$company, $owner]), [
            'password' => 'Temporaria@2026',
            'password_confirmation' => 'Temporaria@2026',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('Temporaria@2026', $owner->refresh()->password));
        $this->assertTrue($owner->must_change_password);
        $this->assertDatabaseHas('platform_activity_logs', [
            'company_id' => $company->id,
            'target_user_id' => $owner->id,
            'action' => 'owner_password_reset',
        ]);
    }

    public function test_owner_with_temporary_password_must_choose_a_new_one(): void
    {
        $owner = User::factory()->admin()->create(['must_change_password' => true]);

        $blockedResponse = $this->actingAs($owner)->get(route('dashboard'));
        $blockedResponse->assertRedirect(route('password.required.edit'));

        $changeResponse = $this->actingAs($owner)->put(route('password.required.update'), [
            'password' => 'NovaSenha@2026',
            'password_confirmation' => 'NovaSenha@2026',
        ]);

        $changeResponse->assertRedirect(route('dashboard'));
        $this->assertFalse($owner->refresh()->must_change_password);
        $this->assertTrue(Hash::check('NovaSenha@2026', $owner->password));
    }

    public function test_platform_admin_cannot_reset_user_from_another_company_as_owner(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create();
        $unrelatedUser = User::factory()->admin()->create();

        $response = $this->actingAs($platformAdmin)->put(route('platform.owners.password.update', [$company, $unrelatedUser]), [
            'password' => 'Temporaria@2026',
            'password_confirmation' => 'Temporaria@2026',
        ]);

        $response->assertNotFound();
    }
}
