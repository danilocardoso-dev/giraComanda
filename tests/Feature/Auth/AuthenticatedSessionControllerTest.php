<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Entrar no GiraComanda');
    }

    public function test_active_user_can_authenticate(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@giracomanda.local',
            'password' => 'senha-segura',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'ADMIN@GIRACOMANDA.LOCAL',
            'password' => 'senha-segura',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inativo@giracomanda.local',
            'password' => 'senha-segura',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'inativo@giracomanda.local',
            'password' => 'senha-segura',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'E-mail, senha ou situação do usuário inválidos.',
        ]);
        $this->assertGuest();
    }

    public function test_platform_admin_is_redirected_to_the_platform_dashboard(): void
    {
        $user = User::factory()->platformAdmin()->create([
            'email' => 'plataforma@giracomanda.local',
            'password' => 'senha-segura',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'plataforma@giracomanda.local',
            'password' => 'senha-segura',
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
