<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrialRegistrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_view_the_commercial_home_page_and_trial_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('R$ 297')
            ->assertSee('7 dias grátis')
            ->assertSee('Cada consumo.')
            ->assertSee('Na conta certa.')
            ->assertSee('Simulador de ponto de equilíbrio')
            ->assertSee('Não é só mais um PDV')
            ->assertSee('Perguntas frequentes');

        $this->get(route('trial.create'))
            ->assertOk()
            ->assertSee('Começar meus 7 dias grátis');
    }

    public function test_guest_can_create_a_company_with_a_seven_day_trial(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 10:00:00'));

        $response = $this->post(route('trial.store'), [
            'company_name' => 'Restaurante Teste Livre',
            'owner_name' => 'Danilo Cliente',
            'owner_email' => 'danilo@cliente.local',
            'billing_phone' => '(11) 99999-8888',
            'owner_password' => 'senha-segura',
            'owner_password_confirmation' => 'senha-segura',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Seu teste de 7 dias começou. Bem-vindo ao GiraComanda!');
        $owner = User::query()->where('email', 'danilo@cliente.local')->sole();
        $company = Company::query()->where('slug', 'restaurante-teste-livre')->sole();
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'status' => SubscriptionStatus::Trial->value,
            'monthly_amount_cents' => 29700,
            'billing_phone' => '11999998888',
        ]);
        $this->assertSame('2026-10-22', $company->subscription()->sole()->trial_ends_at->toDateString());
    }

    public function test_trial_registration_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'existente@cliente.local']);

        $response = $this->from(route('trial.create'))->post(route('trial.store'), [
            'company_name' => 'Empresa duplicada',
            'owner_name' => 'Cliente Existente',
            'owner_email' => 'existente@cliente.local',
            'billing_phone' => '11999998888',
            'owner_password' => 'senha-segura',
            'owner_password_confirmation' => 'senha-segura',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('trial.create'));
        $response->assertSessionHasErrors([
            'owner_email' => 'Este e-mail já possui acesso ao GiraComanda.',
        ]);
        $this->assertDatabaseMissing('companies', ['name' => 'Empresa duplicada']);
    }
}
