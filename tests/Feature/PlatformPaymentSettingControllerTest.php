<?php

namespace Tests\Feature;

use App\Models\PlatformActivityLog;
use App\Models\PlatformPaymentSetting;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PlatformPaymentSettingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_view_or_update_payment_settings(): void
    {
        $this->get(route('platform.payment-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('platform.payment-settings.update'), ['environment' => 'production'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('platform_payment_settings', 0);
        $this->assertDatabaseMissing('platform_activity_logs', ['action' => 'payment_settings_updated']);
    }

    #[TestWith(['admin'])]
    #[TestWith(['cashier'])]
    #[TestWith(['attendant'])]
    public function test_establishment_roles_cannot_view_or_modify_platform_credentials(string $role): void
    {
        $user = User::factory()->create(['role' => UserRole::from($role)]);
        $settings = PlatformPaymentSetting::factory()->create(['test_access_token' => 'original-test-token']);

        $this->actingAs($user)->get(route('platform.payment-settings.edit'))->assertForbidden();
        $this->put(route('platform.payment-settings.update'), [
            'environment' => 'production',
            'test_access_token' => 'unauthorized-token',
        ])->assertForbidden();

        $this->assertSame('test', $settings->refresh()->environment);
        $this->assertSame('original-test-token', $settings->test_access_token);
        $this->assertDatabaseMissing('platform_activity_logs', ['action' => 'payment_settings_updated']);
    }

    public function test_inactive_platform_admin_cannot_view_or_update_credentials(): void
    {
        $admin = User::factory()->platformAdmin()->create(['active' => false]);

        $this->actingAs($admin)->get(route('platform.payment-settings.edit'))->assertForbidden();
        $this->put(route('platform.payment-settings.update'), ['environment' => 'production'])->assertForbidden();

        $this->assertDatabaseCount('platform_payment_settings', 0);
    }

    public function test_platform_admin_must_change_temporary_password_before_accessing_settings(): void
    {
        $admin = User::factory()->platformAdmin()->create(['must_change_password' => true]);

        $this->actingAs($admin)->get(route('platform.payment-settings.edit'))
            ->assertRedirect(route('password.required.edit'));
        $this->put(route('platform.payment-settings.update'), ['environment' => 'production'])
            ->assertRedirect(route('password.required.edit'));

        $this->assertDatabaseCount('platform_payment_settings', 0);
    }

    public function test_platform_admin_can_view_empty_configuration_without_creating_records(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $admin->companyMemberships()->delete();
        $admin->branchMemberships()->delete();

        $response = $this->actingAs($admin)->get(route('platform.payment-settings.edit'));

        $response->assertOk()
            ->assertSee('Mercado Pago')
            ->assertSee('Public Key')
            ->assertSee('Access Token')
            ->assertSee('Segredo do webhook')
            ->assertSee('Salvar credenciais não inicia cobranças')
            ->assertViewHas('environment', 'test');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('platform_payment_settings', 0);
    }

    public function test_admin_can_save_encrypted_credentials_without_charging_or_changing_subscriptions(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->platformAdmin()->create();
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'monthly_amount_cents' => 29700,
        ]);
        $credentials = [
            'test_public_key' => 'TEST-public-key-example',
            'test_access_token' => 'TEST-access-token-example',
            'test_webhook_secret' => 'test-webhook-secret-example',
            'production_public_key' => 'APP_USR-public-key-example',
            'production_access_token' => 'APP_USR-access-token-example',
            'production_webhook_secret' => 'production-webhook-secret-example',
        ];

        $response = $this->actingAs($admin)->put(route('platform.payment-settings.update'), [
            'environment' => 'test',
            ...$credentials,
            'test_plan_id' => 'test-plan-example',
            'production_plan_id' => 'production-plan-example',
            'provider' => 'unexpected-provider',
        ]);

        $response->assertRedirect(route('platform.payment-settings.edit'))
            ->assertSessionHas('success', 'Configurações do Mercado Pago salvas com segurança.');
        $this->assertDatabaseHas('platform_payment_settings', [
            'provider' => 'mercado_pago',
            'environment' => 'test',
            'test_plan_id' => 'test-plan-example',
            'production_plan_id' => 'production-plan-example',
        ]);
        $this->assertDatabaseCount('platform_payment_settings', 1);
        $settings = PlatformPaymentSetting::query()->sole();
        $log = PlatformActivityLog::query()->where('action', 'payment_settings_updated')->sole();
        $this->assertSame($admin->id, $log->platform_admin_user_id);

        foreach ($credentials as $field => $value) {
            $ciphertext = DB::table('platform_payment_settings')->where('id', $settings->id)->value($field);
            $this->assertNotSame($value, $ciphertext);
            $this->assertSame($value, Crypt::decryptString($ciphertext));
            $this->assertSame($value, $settings->getAttribute($field));
            $this->assertArrayNotHasKey($field, $settings->toArray());
            $this->assertStringNotContainsString($value, $log->description);
        }

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertSame(29700, $subscription->monthly_amount_cents);
        $this->assertDatabaseCount('subscription_invoices', 0);
        Http::assertNothingSent();
    }

    public function test_saved_credentials_are_not_rendered_in_html_or_view_data(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $credentials = [
            'test_public_key' => 'hidden-test-public-value',
            'test_access_token' => 'hidden-test-token-value',
            'test_webhook_secret' => 'hidden-test-webhook-value',
            'production_public_key' => 'hidden-production-public-value',
            'production_access_token' => 'hidden-production-token-value',
            'production_webhook_secret' => 'hidden-production-webhook-value',
        ];
        PlatformPaymentSetting::factory()->create($credentials);

        $response = $this->actingAs($admin)->get(route('platform.payment-settings.edit'));

        $response->assertOk()->assertSee('Credencial salva');
        $viewData = json_encode($response->viewData('environments'));

        foreach ($credentials as $value) {
            $response->assertDontSee($value);
            $this->assertStringNotContainsString($value, $viewData);
        }
    }

    public function test_blank_fields_preserve_credentials_while_an_explicit_value_replaces_only_one(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $settings = PlatformPaymentSetting::factory()->create([
            'test_public_key' => 'saved-public-key',
            'test_access_token' => 'saved-test-token',
            'production_access_token' => 'saved-production-token',
            'production_plan_id' => 'saved-production-plan',
        ]);

        $response = $this->actingAs($admin)->put(route('platform.payment-settings.update'), [
            'environment' => 'production',
            'test_public_key' => '',
            'test_access_token' => 'replacement-test-token',
            'production_access_token' => null,
        ]);

        $response->assertRedirect(route('platform.payment-settings.edit'))->assertSessionHasNoErrors();
        $this->assertSame('production', $settings->refresh()->environment);
        $this->assertSame('saved-public-key', $settings->test_public_key);
        $this->assertSame('replacement-test-token', $settings->test_access_token);
        $this->assertSame('saved-production-token', $settings->production_access_token);
        $this->assertSame('saved-production-plan', $settings->production_plan_id);
        $this->assertDatabaseCount('platform_payment_settings', 1);
    }

    public function test_admin_can_explicitly_remove_one_credential_without_removing_the_other_environment(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $settings = PlatformPaymentSetting::factory()->create([
            'test_access_token' => 'preserved-test-token',
            'production_access_token' => 'removed-production-token',
        ]);

        $response = $this->actingAs($admin)->put(route('platform.payment-settings.update'), [
            'environment' => 'test',
            'remove_production_access_token' => '1',
        ]);

        $response->assertRedirect(route('platform.payment-settings.edit'))->assertSessionHasNoErrors();
        $this->assertSame('preserved-test-token', $settings->refresh()->test_access_token);
        $this->assertNull($settings->production_access_token);
    }

    public function test_validation_errors_never_flash_credentials_or_save_partial_changes(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $credentials = [
            'test_public_key' => 'secret-test-public',
            'test_access_token' => 'secret-test-token',
            'test_webhook_secret' => 'secret-test-webhook',
            'production_public_key' => 'secret-production-public',
            'production_access_token' => 'secret-production-token',
            'production_webhook_secret' => 'secret-production-webhook',
        ];

        $response = $this->actingAs($admin)->from(route('platform.payment-settings.edit'))
            ->put(route('platform.payment-settings.update'), [
                'environment' => 'invalid',
                ...$credentials,
                'test_plan_id' => '<script>alert(1)</script>',
            ]);

        $response->assertRedirect(route('platform.payment-settings.edit'))
            ->assertSessionHasErrors(['environment' => 'Escolha Testes ou Produção.']);

        foreach (array_keys($credentials) as $field) {
            $response->assertSessionMissing('_old_input.'.$field);
        }

        $this->assertDatabaseCount('platform_payment_settings', 0);
        $this->assertDatabaseMissing('platform_activity_logs', ['action' => 'payment_settings_updated']);
        $this->get(route('platform.payment-settings.edit'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_validation_failure_preserves_existing_credentials(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $settings = PlatformPaymentSetting::factory()->create(['test_access_token' => 'preserved-token']);

        $response = $this->actingAs($admin)->put(route('platform.payment-settings.update'), [
            'environment' => 'test',
            'test_access_token' => 'new-token',
            'remove_test_access_token' => '1',
        ]);

        $response->assertSessionHasErrors(['test_access_token' => 'Escolha entre substituir a credencial ou removê-la.']);
        $this->assertSame('preserved-token', $settings->refresh()->test_access_token);
        $this->assertDatabaseMissing('platform_activity_logs', ['action' => 'payment_settings_updated']);
    }

    #[TestWith(['test_access_token', 'token with spaces', 'Cole somente a chave, sem espaços ou quebras de linha.'])]
    #[TestWith(['production_plan_id', 'https://mpago.la/example', 'Informe apenas o ID do plano, sem o link de pagamento.'])]
    #[TestWith(['remove_test_access_token', 'yes', 'A opção de remoção é inválida.'])]
    public function test_rejects_invalid_credential_configuration(string $field, string $value, string $message): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->put(route('platform.payment-settings.update'), [
            'environment' => 'test',
            $field => $value,
        ]);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertDatabaseCount('platform_payment_settings', 0);
    }

    public function test_requires_an_environment_and_rejects_oversized_credentials(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->put(route('platform.payment-settings.update'), [
            'test_access_token' => str_repeat('a', 4097),
        ]);

        $response->assertSessionHasErrors([
            'environment' => 'Escolha o ambiente da integração.',
            'test_access_token' => 'O conteúdo informado ultrapassa o tamanho permitido.',
        ]);
        $response->assertSessionMissing('_old_input.test_access_token');
        $this->assertDatabaseCount('platform_payment_settings', 0);
    }
}
