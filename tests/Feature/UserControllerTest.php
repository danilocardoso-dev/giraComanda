<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_cannot_manage_users(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->get(route('users.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_cashier(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Operador do Caixa',
            'email' => 'caixa@giracomanda.local',
            'role' => UserRole::Cashier->value,
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'active' => '1',
        ]);

        $cashier = User::query()->where('email', 'caixa@giracomanda.local')->sole();

        $response->assertRedirect(route('users.edit', $cashier));
        $this->assertSame(UserRole::Cashier, $cashier->role);
        $this->assertTrue(Hash::check('senha-segura', $cashier->password));
    }

    public function test_admin_cannot_deactivate_own_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Admin->value,
            'password' => '',
            'password_confirmation' => '',
            'active' => '0',
        ]);

        $response->assertSessionHasErrors([
            'active' => 'Você não pode desativar o próprio usuário.',
        ]);
        $this->assertTrue($admin->fresh()->active);
    }
}
