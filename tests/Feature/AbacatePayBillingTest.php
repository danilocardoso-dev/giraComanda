<?php

namespace Tests\Feature;

use App\CompanyRole;
use App\Models\Branch;
use App\Models\CompanyMembership;
use App\Models\PlatformPaymentSetting;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionGatewayPayment;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\SubscriptionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AbacatePayBillingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_generates_one_pix_for_the_invoice_and_reuses_it(): void
    {
        $this->freezeTime();
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        Http::preventStrayRequests();
        Http::fake(['api.abacatepay.com/v2/transparents/create' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'pix_char_123', 'status' => 'PENDING', 'brCode' => '000201-realistic-test-pix', 'expiresAt' => now()->addDay()->toIso8601String(),
        ]])]);

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])
            ->assertRedirect(route('subscription.show'))->assertSessionHasNoErrors();
        $this->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])
            ->assertRedirect(route('subscription.show'))->assertSessionHasNoErrors();

        $checkout = SubscriptionCheckout::query()->sole();
        $this->assertSame($invoice->id, $checkout->subscription_invoice_id);
        $this->assertSame('pix_char_123', $checkout->remote_id);
        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertSame(SubscriptionStatus::Trial, $subscription->refresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request): bool => $request['method'] === 'PIX'
            && $request['data']['amount'] === 29700
            && $request['data']['metadata']['checkoutUuid'] === $checkout->uuid
            && $request->hasHeader('Authorization', 'Bearer prod-api-key'));
        $this->get(route('subscription.show'))->assertOk()->assertSee('Copiar PIX copia e cola')->assertSee('000201-realistic-test-pix');
    }

    public function test_checkout_is_unavailable_without_enabled_configuration(): void
    {
        $this->settings(['enabled' => false]);
        [$owner] = $this->customer();
        Http::preventStrayRequests();

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('subscription_checkouts', 0);
        Http::assertNothingSent();
    }

    public function test_guest_and_company_member_cannot_start_payments(): void
    {
        $this->post(route('subscription.checkouts.store'), ['kind' => 'pix'])->assertRedirect(route('login'));

        [$owner] = $this->customer();
        CompanyMembership::query()->where('user_id', $owner->id)->update(['role' => CompanyRole::Member]);
        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])->assertForbidden();

        $this->assertDatabaseCount('subscription_checkouts', 0);
    }

    public function test_owner_cannot_consult_or_cancel_another_company_checkout(): void
    {
        [$owner] = $this->customer();
        $other = SubscriptionCheckout::factory()->create();
        Http::preventStrayRequests();

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $other))->assertNotFound();
        $this->delete(route('subscription.checkouts.destroy', $other))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_card_requires_explicit_recurring_consent(): void
    {
        [$owner] = $this->customer();

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'card', 'payer_email' => 'buyer@example.com'])
            ->assertSessionHasErrors(['recurring_consent' => 'Confirme a autorização da assinatura recorrente para continuar.']);

        $this->assertDatabaseCount('subscription_checkouts', 0);
    }

    public function test_card_creates_product_and_hosted_subscription_checkout_without_releasing_access(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        Http::preventStrayRequests();
        Http::fake([
            'api.abacatepay.com/v2/products/create' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'prod_123']]),
            'api.abacatepay.com/v2/subscriptions/create' => Http::response(['success' => true, 'error' => null, 'data' => [
                'id' => 'bill_123', 'status' => 'PENDING', 'url' => 'https://app.abacatepay.com/pay/bill_123',
            ]]),
        ]);

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'card', 'payer_email' => 'buyer@example.com', 'recurring_consent' => '1'])
            ->assertRedirect('https://app.abacatepay.com/pay/bill_123');

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertFalse($subscription->refresh()->allowsAccess());
        $checkout = SubscriptionCheckout::query()->sole();
        $this->assertSame('bill_123', $checkout->remote_id);
        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/products/create')
            && $request['price'] === 29700 && $request['cycle'] === 'MONTHLY' && $request['externalId'] === $checkout->uuid);
        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/subscriptions/create')
            && $request['items'][0]['id'] === 'prod_123' && $request['methods'] === ['CARD'] && $request['externalId'] === $checkout->uuid);
        // A forged return parameter cannot activate access.
        $this->get(route('subscription.show', ['status' => 'approved']))->assertOk();
        $this->assertFalse($subscription->refresh()->allowsAccess());
    }

    public function test_checkout_url_outside_abacatepay_is_rejected(): void
    {
        $this->settings();
        [$owner] = $this->customer();
        Http::preventStrayRequests();
        Http::fake([
            'api.abacatepay.com/v2/products/create' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'prod_123']]),
            'api.abacatepay.com/v2/subscriptions/create' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'bill_1', 'url' => 'https://evil.example.com/pay']]),
        ]);

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'card', 'payer_email' => 'buyer@example.com', 'recurring_consent' => '1'])
            ->assertSessionHasErrors('payment');
    }

    public function test_webhook_requires_the_configured_secret(): void
    {
        $this->postJson(route('abacatepay.webhook', ['environment' => 'production']), [])->assertStatus(503);

        $this->settings();
        $this->postJson(route('abacatepay.webhook', ['environment' => 'production']), ['event' => 'transparent.completed'])->assertUnauthorized();
        $this->postJson(route('abacatepay.webhook', ['environment' => 'production']).'?webhookSecret=wrong', ['event' => 'transparent.completed'])->assertUnauthorized();
    }

    public function test_pix_webhook_rereads_the_charge_and_settles_the_invoice_once(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.abacatepay.com/v2/transparents/get*' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'pix_char_123', 'status' => 'PAID', 'amount' => 29700, 'updatedAt' => now()->toIso8601String(),
        ]])]);

        $this->webhook('transparent.completed', ['transparent' => ['id' => 'pix_char_123', 'status' => 'PAID']])->assertOk();
        $this->webhook('transparent.completed', ['transparent' => ['id' => 'pix_char_123', 'status' => 'PAID']])->assertOk();

        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertTrue($subscription->refresh()->allowsAccess());
        $this->assertSame('approved', $checkout->refresh()->status);
        $this->assertSame(1, SubscriptionGatewayPayment::query()->whereNotNull('applied_at')->count());
    }

    public function test_pix_with_different_amount_requires_review_and_does_not_release_access(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.abacatepay.com/v2/transparents/get*' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'pix_char_123', 'status' => 'PAID', 'amount' => 100, 'updatedAt' => now()->toIso8601String(),
        ]])]);

        $this->webhook('transparent.completed', ['transparent' => ['id' => 'pix_char_123']])->assertOk();

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertSame('amount_currency_or_method_mismatch', SubscriptionGatewayPayment::query()->sole()->review_reason);
    }

    public function test_test_environment_payment_is_recorded_but_never_settles(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $this->pix($subscription, $invoice, ['environment' => 'test']);
        Http::preventStrayRequests();
        Http::fake(['api.abacatepay.com/v2/transparents/get*' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'pix_char_123', 'status' => 'PAID', 'amount' => 29700,
        ]])]);

        $this->webhook('transparent.completed', ['transparent' => ['id' => 'pix_char_123']], environment: 'test', devMode: true)->assertOk();

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertSame('test_payment_not_applied', SubscriptionGatewayPayment::query()->sole()->review_reason);
    }

    public function test_dev_mode_event_is_ignored_in_production(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $this->pix($subscription, $invoice);
        Http::preventStrayRequests();

        $this->webhook('transparent.completed', ['transparent' => ['id' => 'pix_char_123']], devMode: true)->assertOk();

        Http::assertNothingSent();
        $this->assertNull($invoice->refresh()->paid_at);
    }

    public function test_subscription_completed_settles_first_invoice_and_links_the_subscription(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);

        $payload = $this->subscriptionPayload($checkout, 'char_1');
        $this->webhook('subscription.completed', $payload)->assertOk();
        $this->webhook('subscription.completed', $payload)->assertOk();

        $checkout->refresh();
        $this->assertSame('subs_123', $checkout->provider_subscription_id);
        $this->assertSame('authorized', $checkout->status);
        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertTrue($subscription->refresh()->allowsAccess());
        $this->assertSame(1, SubscriptionGatewayPayment::query()->count());
    }

    public function test_subscription_renewed_creates_and_settles_the_next_cycle_invoice(): void
    {
        $this->freezeTime();
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        $this->webhook('subscription.completed', $this->subscriptionPayload($checkout, 'char_1'))->assertOk();

        $this->travel(1)->months();
        $this->webhook('subscription.renewed', $this->subscriptionPayload($checkout, 'char_2', 'bill_renewal_999'))->assertOk();

        $this->assertSame(2, SubscriptionInvoice::query()->where('subscription_id', $subscription->id)->whereNotNull('paid_at')->count());
        $this->assertSame(2, SubscriptionGatewayPayment::query()->whereNotNull('applied_at')->count());
    }

    public function test_yearly_card_subscription_grants_a_full_year_of_access(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $invoice->update(['amount_cents' => 297000]);
        $checkout = $this->card($subscription, $invoice, ['billing_cycle' => 'yearly', 'amount_cents' => 297000]);

        $this->webhook('subscription.completed', $this->subscriptionPayload($checkout, 'char_1', null, 297000))->assertOk();

        $subscription->refresh();
        $this->assertSame('yearly', $subscription->billing_cycle);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertGreaterThan(300, now()->diffInDays($subscription->paid_until));
    }

    public function test_subscription_cancelled_event_closes_the_recurrence(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice, ['status' => 'authorized', 'provider_subscription_id' => 'subs_123']);

        $this->webhook('subscription.cancelled', ['subscription' => ['id' => 'subs_123', 'status' => 'CANCELLED']])->assertOk();

        $this->assertSame('cancelled', $checkout->refresh()->status);
    }

    public function test_owner_cancels_an_active_recurrence_through_the_api(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice, ['status' => 'authorized', 'provider_subscription_id' => 'subs_123', 'environment' => 'test']);
        Http::preventStrayRequests();
        Http::fake([
            'api.abacatepay.com/v2/subscriptions/checkouts/get*' => Http::response(['success' => true, 'error' => null, 'data' => [
                'id' => 'bill_123', 'externalId' => $checkout->uuid, 'status' => 'PAID', 'amount' => 29700, 'url' => 'https://app.abacatepay.com/pay/bill_123',
            ]]),
            'api.abacatepay.com/v2/subscriptions/get*' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'subs_123', 'status' => 'ACTIVE']]),
            'api.abacatepay.com/v2/subscriptions/cancel' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'subs_123', 'status' => 'CANCELLED']]),
        ]);

        $this->actingAs($owner)->delete(route('subscription.checkouts.destroy', $checkout))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $checkout->refresh()->status);
        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/subscriptions/cancel') && $request['id'] === 'subs_123');
    }

    public function test_consulting_a_pending_subscription_checkout_syncs_status_without_paying(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice, ['environment' => 'test']);
        Http::preventStrayRequests();
        Http::fake(['api.abacatepay.com/v2/subscriptions/checkouts/get*' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'bill_123', 'externalId' => $checkout->uuid, 'status' => 'EXPIRED', 'amount' => 29700,
        ]])]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $checkout->refresh()->status);
        $this->assertNull($invoice->refresh()->paid_at);
    }

    public function test_reconcile_command_consults_open_checkouts(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.abacatepay.com/v2/transparents/get*' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'pix_char_123', 'status' => 'PENDING', 'amount' => 29700,
        ]])]);

        $this->artisan('subscriptions:reconcile-abacatepay')->assertSuccessful();

        Http::assertSentCount(1);
    }

    private function settings(array $overrides = []): PlatformPaymentSetting
    {
        return PlatformPaymentSetting::factory()->create(['environment' => 'production', 'enabled' => true,
            'test_access_token' => 'test-api-key', 'test_webhook_secret' => 'test-secret',
            'production_access_token' => 'prod-api-key', 'production_webhook_secret' => 'test-secret', ...$overrides]);
    }

    private function customer(): array
    {
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Trial,
            'trial_ends_at' => today()->subDay(), 'monthly_amount_cents' => 29700]);
        Branch::factory()->for($subscription->company)->create();
        $owner = User::factory()->admin()->create();
        $owner->companyMemberships()->delete();
        CompanyMembership::factory()->create(['company_id' => $subscription->company_id, 'user_id' => $owner->id, 'role' => CompanyRole::CompanyAdmin, 'active' => true]);
        $invoice = SubscriptionInvoice::factory()->for($subscription)->create(['amount_cents' => 29700, 'due_date' => today()]);

        return [$owner, $subscription, $invoice];
    }

    private function pix(Subscription $subscription, SubscriptionInvoice $invoice, array $overrides = []): SubscriptionCheckout
    {
        return SubscriptionCheckout::factory()->create(['subscription_id' => $subscription->id, 'subscription_invoice_id' => $invoice->id,
            'remote_id' => 'pix_char_123', 'environment' => 'production', ...$overrides]);
    }

    private function card(Subscription $subscription, SubscriptionInvoice $invoice, array $overrides = []): SubscriptionCheckout
    {
        return SubscriptionCheckout::factory()->create(['subscription_id' => $subscription->id, 'subscription_invoice_id' => $invoice->id,
            'kind' => 'card', 'status' => 'pending', 'remote_id' => 'bill_123', 'pix_code' => null, 'environment' => 'production',
            'checkout_url' => 'https://app.abacatepay.com/pay/bill_123', ...$overrides]);
    }

    /** @return array<string, mixed> */
    private function subscriptionPayload(SubscriptionCheckout $checkout, string $paymentId, ?string $billId = null, int $amount = 29700): array
    {
        return [
            'subscription' => ['id' => 'subs_123', 'amount' => $amount, 'status' => 'ACTIVE'],
            'payment' => ['id' => $paymentId, 'amount' => $amount, 'paidAmount' => $amount, 'status' => 'PAID', 'updatedAt' => now()->toIso8601String()],
            'checkout' => ['id' => $billId ?? $checkout->remote_id, 'status' => 'PAID'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function webhook(string $event, array $data, string $environment = 'production', bool $devMode = false): TestResponse
    {
        return $this->postJson(route('abacatepay.webhook', ['environment' => $environment]).'?webhookSecret=test-secret',
            ['id' => 'log_'.uniqid(), 'event' => $event, 'apiVersion' => 2, 'devMode' => $devMode, 'data' => $data]);
    }
}
