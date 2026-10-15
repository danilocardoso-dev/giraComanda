<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Atendimento rápido, controle simples.');
        $response->assertSee('GiraComanda');
        $response->assertSee(route('tabs.index', ['status' => 'open']), false);
        $response->assertSee(route('customers.index', ['status' => 'active']), false);
        $response->assertSee(route('cards.index', ['status' => 'available']), false);
        $response->assertSee(route('cards.index', ['status' => 'blocked']), false);
        $response->assertSee(route('users.index'), false);
    }
}
