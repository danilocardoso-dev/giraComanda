<?php

namespace Tests\Feature;

use App\Actions\SynchronizeMercadoPago;
use App\Actions\SynchronizeSubscriptionInvoices;
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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MercadoPagoBillingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_generates_one_pix_for_the_invoice_and_reuses_it(): void
    {
        $this->freezeTime();
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments' => Http::response([
            'id' => 12345, 'status' => 'pending', 'date_of_expiration' => now()->addDay()->toIso8601String(),
            'point_of_interaction' => ['transaction_data' => ['qr_code' => '000201-realistic-test-pix']],
        ], 201)]);

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])
            ->assertRedirect(route('subscription.show'))->assertSessionHasNoErrors();
        $this->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])
            ->assertRedirect(route('subscription.show'))->assertSessionHasNoErrors();

        $checkout = SubscriptionCheckout::query()->sole();
        $this->assertSame($invoice->id, $checkout->subscription_invoice_id);
        $this->assertSame('12345', $checkout->remote_id);
        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertSame(SubscriptionStatus::Trial, $subscription->refresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request): bool => $request['transaction_amount'] === 297
            && $request['external_reference'] === $checkout->uuid
            && $request->hasHeader('X-Idempotency-Key', $checkout->uuid)
            && str_ends_with($request['notification_url'], '/webhooks/mercado-pago/test'));
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

    public function test_guest_cannot_create_checkout(): void
    {
        $this->post(route('subscription.checkouts.store'), ['kind' => 'pix'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('subscription_checkouts', 0);
    }

    public function test_company_member_cannot_start_payments(): void
    {
        [$owner, $subscription] = $this->customer();
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

    public function test_card_creates_individual_pending_subscription_without_releasing_access(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval' => Http::response([
            'id' => 'rec-123', 'status' => 'pending', 'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=rec-123',
        ], 201)]);

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'card', 'payer_email' => 'buyer@example.com', 'recurring_consent' => '1'])
            ->assertRedirect('https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=rec-123');

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertFalse($subscription->refresh()->allowsAccess());
        $checkout = SubscriptionCheckout::query()->sole();
        Http::assertSent(fn (ClientRequest $request): bool => $request['external_reference'] === $checkout->uuid
            && $request['status'] === 'pending' && $request['auto_recurring']['transaction_amount'] === 297
            && ! isset($request['card_token_id']));
        // A forged return parameter cannot activate access.
        $this->get(route('subscription.show', ['status' => 'approved']))->assertOk();
        $this->assertFalse($subscription->refresh()->allowsAccess());
    }

    public function test_failed_pix_creation_retries_with_same_idempotency_key(): void
    {
        $this->settings();
        [$owner] = $this->customer();
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments' => Http::sequence()->pushStatus(503)->push([
            'id' => 12345, 'status' => 'pending', 'point_of_interaction' => ['transaction_data' => ['qr_code' => '000201-pix']],
        ], 201)]);
        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])->assertSessionHasErrors('payment');
        $uuid = SubscriptionCheckout::query()->sole()->uuid;

        $this->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('subscription_checkouts', 1);
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('X-Idempotency-Key', $uuid));
        }
    }

    public function test_ambiguous_card_creation_is_searched_instead_of_creating_twice(): void
    {
        $this->settings();
        [$owner] = $this->customer();
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::failedConnection(),
            'api.mercadopago.com/preapproval/search*' => Http::response(['results' => []]),
        ]);
        $payload = ['kind' => 'card', 'payer_email' => 'buyer@example.com', 'recurring_consent' => '1'];

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), $payload)->assertSessionHasErrors('payment');
        $this->post(route('subscription.checkouts.store'), $payload)->assertSessionHasErrors('payment');

        Http::assertSentCount(2);
        $this->assertCount(1, Http::recorded(fn (ClientRequest $request): bool => $request->method() === 'POST'));
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/preapproval/search'));
        $this->assertDatabaseCount('subscription_checkouts', 1);
    }

    public function test_unsigned_webhook_returns_401_without_reading_provider_or_paying_invoice(): void
    {
        $this->settings();
        Http::preventStrayRequests();
        $this->postJson(route('mercado-pago.webhook', ['environment' => 'test']).'?data.id=12345', ['type' => 'payment'])
            ->assertUnauthorized();
        Http::assertNothingSent();
        $this->assertDatabaseCount('subscription_gateway_payments', 0);
    }

    public function test_webhook_without_secret_returns_503(): void
    {
        $this->postJson(route('mercado-pago.webhook', ['environment' => 'test']), [])->assertStatus(503);
    }

    public function test_approved_pix_is_applied_once_and_access_expires_after_paid_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->webhook('12345')->assertOk()->assertJson(['received' => true]);
        $this->webhook('12345')->assertOk();

        $this->assertSame('pix', $invoice->refresh()->payment_method);
        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertSame('2026-11-20 23:59:59', $subscription->paid_until->format('Y-m-d H:i:s'));
        $this->assertTrue($subscription->allowsAccess());
        $this->assertDatabaseCount('subscription_gateway_payments', 1);
        $this->assertDatabaseCount('platform_activity_logs', 1);
        $this->assertNotNull(SubscriptionGatewayPayment::query()->sole()->applied_at);
        $this->travelTo(CarbonImmutable::parse('2026-11-21 00:00:01'));
        $this->assertFalse($subscription->refresh()->allowsAccess());
        $this->assertSame(0, $owner->accessibleBranchesQuery()->where('company_id', $subscription->company_id)->count());
    }

    #[TestWith(['pending'])]
    #[TestWith(['rejected'])]
    #[TestWith(['in_process'])]
    public function test_nonapproved_payment_does_not_release_access(string $status): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout, ['status' => $status]))]);

        $this->webhook('12345')->assertOk();

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertFalse($subscription->refresh()->allowsAccess());
        $this->assertNull(SubscriptionGatewayPayment::query()->sole()->applied_at);
    }

    #[TestWith(['transaction_amount', 1])]
    #[TestWith(['currency_id', 'USD'])]
    #[TestWith(['payment_method_id', 'visa'])]
    public function test_mismatched_payment_is_flagged_without_paying_invoice(string $field, mixed $value): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout, [$field => $value]))]);

        $this->webhook('12345')->assertOk();

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertSame('amount_currency_or_method_mismatch', SubscriptionGatewayPayment::query()->sole()->review_reason);
    }

    public function test_test_notification_cannot_apply_a_live_payment(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout, ['live_mode' => false]))]);

        $this->webhook('12345')->assertStatus(503);

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertDatabaseCount('subscription_gateway_payments', 0);
    }

    public function test_provider_failure_returns_503_for_retry_without_changing_access(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response([], 500)]);

        $this->webhook('12345')->assertStatus(503);

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertDatabaseCount('subscription_gateway_payments', 0);
    }

    public function test_payment_records_receipt_but_preserves_admin_suspension(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $subscription->update(['status' => SubscriptionStatus::Suspended]);
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->webhook('12345')->assertOk();

        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->refresh()->status);
        $this->assertFalse($subscription->allowsAccess());
    }

    public function test_manually_paid_invoice_is_not_applied_or_credited_twice(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        $invoice->update(['paid_at' => now(), 'payment_method' => 'pix']);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->webhook('12345')->assertOk();

        $this->assertNull($subscription->refresh()->paid_until);
        $this->assertSame('invoice_already_paid_or_changed', SubscriptionGatewayPayment::query()->sole()->review_reason);
    }

    public function test_authorized_subscription_alone_does_not_pay_invoice(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval/rec-123' => Http::response($this->recurrenceData($checkout))]);

        $this->webhook('rec-123', 'subscription_preapproval')->assertOk();

        $this->assertSame('authorized', $checkout->refresh()->status);
        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertFalse($subscription->refresh()->allowsAccess());
    }

    public function test_recurring_payment_releases_first_month_and_next_cycle_without_duplicate_invoices(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/authorized_payments/555' => Http::response(['id' => 555, 'preapproval_id' => 'rec-123', 'external_reference' => $checkout->uuid, 'debit_date' => '2026-10-20T12:00:00-03:00', 'payment' => ['id' => 12345]]),
            'api.mercadopago.com/authorized_payments/556' => Http::response(['id' => 556, 'preapproval_id' => 'rec-123', 'external_reference' => $checkout->uuid, 'debit_date' => '2026-11-20T12:00:00-03:00', 'payment' => ['id' => 12346]]),
            'api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout, ['payment_type_id' => 'credit_card', 'payment_method_id' => 'visa'])),
            'api.mercadopago.com/v1/payments/12346' => Http::response($this->paymentData($checkout, ['id' => 12346, 'date_approved' => '2026-11-20T12:00:00-03:00', 'payment_type_id' => 'credit_card', 'payment_method_id' => 'visa'])),
        ]);

        $this->webhook('555', 'subscription_authorized_payment')->assertOk();
        $this->assertSame('credit_card', $invoice->refresh()->payment_method);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:01:00'));
        $this->webhook('556', 'subscription_authorized_payment')->assertOk();
        $this->webhook('556', 'subscription_authorized_payment')->assertOk();

        $this->assertSame('2026-12-20 23:59:59', $subscription->refresh()->paid_until->format('Y-m-d H:i:s'));
        $this->assertSame(2, $subscription->invoices()->count());
        $this->assertSame(2, SubscriptionGatewayPayment::query()->whereNotNull('applied_at')->count());
    }

    public function test_existing_card_blocks_pix_and_manual_settlement_until_cancelled(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $this->card($subscription, $invoice);
        Http::preventStrayRequests();

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])->assertSessionHasErrors('payment');
        $this->actingAs(User::factory()->platformAdmin()->create())->post(route('platform.invoices.payments.store', [$subscription->company, $invoice]), [
            'paid_on' => today()->toDateString(), 'payment_method' => 'pix', 'reactivate_access' => '1',
        ])->assertUnprocessable();

        $this->assertNull($invoice->refresh()->paid_at);
        Http::assertNothingSent();
    }

    public function test_cancelled_card_keeps_paid_period_and_allows_pix_afterwards(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $subscription->update(['status' => SubscriptionStatus::Active, 'paid_until' => now()->addDays(15)]);
        $checkout = $this->card($subscription, $invoice);
        $before = $subscription->paid_until->toIso8601String();
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval/rec-123' => Http::sequence()->push($this->recurrenceData($checkout))->push(['status' => 'cancelled'])->push($this->recurrenceData($checkout, ['status' => 'cancelled'])),
            'api.mercadopago.com/authorized_payments/search*' => Http::response(['results' => []]),
        ]);

        $this->actingAs($owner)->delete(route('subscription.checkouts.destroy', $checkout))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $checkout->refresh()->status);
        $this->assertSame($before, $subscription->refresh()->paid_until->toIso8601String());
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT' && $request['status'] === 'cancelled');
    }

    public function test_master_panel_lists_gateway_history_and_controls(): void
    {
        [, $subscription, $invoice] = $this->customer();
        $this->card($subscription, $invoice);
        $this->actingAs(User::factory()->platformAdmin()->create())->get(route('platform.companies.show', $subscription->company))
            ->assertOk()->assertSee('Cancelar recorrência')->assertSee('rec-123');
    }

    public function test_enabled_configuration_requires_both_token_and_webhook_secret(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('platform.payment-settings.update'), ['environment' => 'production', 'enabled' => '1'])
            ->assertSessionHasErrors(['production_access_token', 'production_webhook_secret']);
        $this->assertDatabaseCount('platform_payment_settings', 0);
    }

    public function test_reconciliation_command_recovers_missing_webhook(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->artisan('subscriptions:reconcile-mercado-pago')->assertSuccessful();

        $this->assertNotNull($invoice->refresh()->paid_at);
        Http::assertSentCount(1);
    }

    public function test_test_payment_is_confirmed_in_history_without_paying_real_invoice(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        $checkout->update(['environment' => 'test']);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->webhook('12345', 'payment', 'test')->assertOk();

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertFalse($subscription->refresh()->allowsAccess());
        $this->assertSame('test_payment_not_applied', SubscriptionGatewayPayment::query()->sole()->review_reason);
        $this->assertNull(SubscriptionGatewayPayment::query()->sole()->applied_at);
    }

    public function test_signed_query_and_body_resource_must_match(): void
    {
        $this->settings();
        Http::preventStrayRequests();
        $timestamp = (string) now()->timestamp;
        $hash = hash_hmac('sha256', 'id:12345;request-id:test-request;ts:'.$timestamp.';', 'test-secret');

        $this->postJson(route('mercado-pago.webhook', ['environment' => 'test']).'?data.id=12345', ['type' => 'payment', 'data' => ['id' => '67890']],
            ['x-request-id' => 'test-request', 'x-signature' => 'ts='.$timestamp.',v1='.$hash])->assertBadRequest();

        Http::assertNothingSent();
    }

    public function test_signed_webhook_is_exempt_from_csrf_but_payment_forms_are_not(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout, ['status' => 'pending']))]);
        $this->app['env'] = 'production';

        $this->webhook('12345')->assertOk();
        $this->post(route('subscription.checkouts.store'), ['kind' => 'pix'])->assertStatus(419);

        $this->assertNull($invoice->refresh()->paid_at);
        Http::assertSentCount(1);
    }

    public function test_trial_period_cannot_be_charged_before_expiring(): void
    {
        $this->settings();
        [$owner, $subscription] = $this->customer();
        $subscription->update(['trial_ends_at' => today()->addDays(3)]);
        Http::preventStrayRequests();

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('subscription_checkouts', 0);
        Http::assertNothingSent();
    }

    public function test_refund_is_flagged_once_for_manual_review_without_reversing_admin_decisions(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::sequence()
            ->push($this->paymentData($checkout))->push($this->paymentData($checkout, ['status' => 'refunded']))
            ->push($this->paymentData($checkout, ['status' => 'refunded']))]);

        $this->webhook('12345')->assertOk();
        $until = $subscription->refresh()->paid_until->toIso8601String();
        $this->webhook('12345')->assertOk();
        $this->webhook('12345')->assertOk();

        $this->assertSame('refund_or_chargeback', SubscriptionGatewayPayment::query()->sole()->review_reason);
        $this->assertSame($until, $subscription->refresh()->paid_until->toIso8601String());
        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertDatabaseCount('platform_activity_logs', 2);
    }

    public function test_failed_cancellation_does_not_allow_switching_to_pix(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval/rec-123' => Http::sequence()
            ->push($this->recurrenceData($checkout))->pushStatus(500)]);

        $this->actingAs($owner)->delete(route('subscription.checkouts.destroy', $checkout))->assertSessionHasErrors('payment');
        $this->post(route('subscription.checkouts.store'), ['kind' => 'pix', 'payer_email' => 'buyer@example.com'])->assertSessionHasErrors('payment');

        $this->assertSame('pending', $checkout->refresh()->status);
        $this->assertDatabaseCount('subscription_checkouts', 1);
    }

    public function test_manual_settlement_remains_available_and_extends_an_automatically_managed_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
        [, $subscription, $invoice] = $this->customer();
        $subscription->update(['status' => SubscriptionStatus::Active, 'paid_until' => '2026-11-01 23:59:59']);

        $this->actingAs(User::factory()->platformAdmin()->create())->post(route('platform.invoices.payments.store', [$subscription->company, $invoice]), [
            'paid_on' => '2026-10-20', 'payment_method' => 'pix', 'reactivate_access' => '1',
        ])->assertRedirect();

        $this->assertSame('pix', $invoice->refresh()->payment_method);
        $this->assertSame('2026-12-01 23:59:59', $subscription->refresh()->paid_until->format('Y-m-d H:i:s'));
    }

    public function test_expired_automatic_access_generates_only_one_renewal_without_accumulating_unused_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
        $subscription = Subscription::factory()->create(['paid_until' => '2026-07-10 23:59:59', 'started_at' => '2026-06-10']);

        $synchronize = app(SynchronizeSubscriptionInvoices::class);
        $this->assertSame(1, $synchronize->handle($subscription));
        $this->assertSame(0, $synchronize->handle($subscription));

        $this->assertSame('2026-10-01', $subscription->invoices()->sole()->reference_month->toDateString());
        $this->assertSame('2026-10-20', $subscription->invoices()->sole()->due_date->toDateString());
    }

    public function test_late_first_pix_starts_current_month_without_immediately_creating_another_invoice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $subscription->update(['trial_ends_at' => '2026-09-10']);
        $invoice->update(['reference_month' => '2026-09-01', 'due_date' => '2026-09-11', 'generated_automatically' => true]);
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->webhook('12345')->assertOk();
        app(SynchronizeSubscriptionInvoices::class)->handle($subscription->refresh());

        $this->assertSame('2026-10-01', $invoice->refresh()->reference_month->toDateString());
        $this->assertSame(1, $subscription->invoices()->count());
        $this->assertSame('2026-11-20 23:59:59', $subscription->paid_until->format('Y-m-d H:i:s'));
    }

    public function test_explicit_master_access_override_is_not_replaced_by_automatic_payment(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $subscription->update(['status' => SubscriptionStatus::Active, 'manual_access_override' => true, 'paid_until' => null]);
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->webhook('12345')->assertOk();

        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertNull($subscription->refresh()->paid_until);
        $this->assertTrue($subscription->manual_access_override);
    }

    public function test_http_400_rejects_creation_and_restores_payment_form_with_actual_reason(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval' => Http::response(['message' => 'Both payer and collector must be real or test users'], 400)]);

        $this->actingAs($owner)->from(route('subscription.show'))->post(route('subscription.checkouts.store'), [
            'kind' => 'card', 'payer_email' => 'test-buyer@example.com', 'recurring_consent' => '1',
        ])->assertRedirect(route('subscription.show'))->assertSessionHasErrors('payment')->assertSessionMissing('success');

        $checkout = SubscriptionCheckout::query()->sole();
        $this->assertSame('rejected', $checkout->status);
        $this->assertSame(400, $checkout->failure_http_status);
        $this->assertStringContainsString('Both payer and collector', $checkout->failure_message);
        $this->assertFalse($checkout->isRecurring());
        $this->assertNull($invoice->refresh()->paid_at);
        $this->get(route('subscription.show'))->assertOk()->assertSee('Criação recusada')->assertSee('Assinar com cartão')->assertSee('test-buyer@example.com');
    }

    public function test_definitively_rejected_creation_can_be_retried_with_a_new_reference(): void
    {
        $this->settings();
        [$owner] = $this->customer();
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval' => Http::sequence()->push(['message' => 'Invalid payer_email'], 400)->push([
            'id' => 'rec-retry', 'status' => 'pending', 'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=rec-retry',
        ], 201)]);
        $payload = ['kind' => 'card', 'payer_email' => 'buyer@example.com', 'recurring_consent' => '1'];
        $this->actingAs($owner)->post(route('subscription.checkouts.store'), $payload)->assertSessionHasErrors('payment');
        $first = SubscriptionCheckout::query()->sole();

        $this->post(route('subscription.checkouts.store'), $payload)->assertRedirect('https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=rec-retry');

        $this->assertDatabaseCount('subscription_checkouts', 2);
        $this->assertNotSame($first->uuid, SubscriptionCheckout::query()->latest('id')->first()->uuid);
        $this->assertSame('rejected', $first->refresh()->status);
        Http::assertSentCount(2);
    }

    public function test_empty_consultation_does_not_claim_success_or_advance_last_successful_sync(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        $checkout->update(['remote_id' => null, 'checkout_url' => null, 'status' => 'creating']);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval/search*' => Http::response(['results' => []])]);

        $this->actingAs($owner)->withSession(['success' => 'Sucesso antigo'])->put(route('subscription.checkouts.update', $checkout))
            ->assertSessionHasErrors('payment')->assertSessionMissing('success')->assertSessionMissing('payment_feedback');

        $this->assertNull($checkout->refresh()->last_synced_at);
        $this->assertNotNull($checkout->last_attempted_at);
        $this->assertSame('creating', $checkout->status);
        $this->assertNull($invoice->refresh()->paid_at);
        Http::assertSentCount(1);
    }

    public function test_malformed_search_response_is_an_error_not_an_empty_success(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        $checkout->update(['remote_id' => null, 'checkout_url' => null, 'status' => 'creating']);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval/search*' => Http::response(['unexpected' => []])]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))
            ->assertSessionHasErrors('payment')->assertSessionMissing('payment_feedback')->assertSessionMissing('success');

        $this->assertNull($checkout->refresh()->last_synced_at);
    }

    public function test_pending_pix_consultation_reports_waiting_not_payment_success(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout, ['status' => 'pending']))]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))
            ->assertSessionHasNoErrors()->assertSessionMissing('success')
            ->assertSessionHas('payment_feedback.level', 'info')
            ->assertSessionHas('payment_feedback.message', 'Pix aguardando pagamento. Nenhuma baixa foi confirmada.');

        $this->assertNull($invoice->refresh()->paid_at);
        $this->assertFalse($subscription->refresh()->allowsAccess());
    }

    public function test_authorized_card_without_payment_is_informational_not_a_green_success(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval/rec-123' => Http::response($this->recurrenceData($checkout)),
            'api.mercadopago.com/authorized_payments/search*' => Http::response(['results' => []]),
        ]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))->assertSessionHasNoErrors()
            ->assertSessionHas('payment_feedback.level', 'info')->assertSessionMissing('success');

        $this->assertStringContainsString('Nenhum pagamento confirmado', session('payment_feedback.message'));
        $this->assertNull($invoice->refresh()->paid_at);
    }

    public function test_consultation_rejects_a_resource_belonging_to_a_different_reference(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval/rec-123' => Http::response($this->recurrenceData($checkout, ['external_reference' => 'another-company']))]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))
            ->assertSessionHasErrors('payment')->assertSessionMissing('payment_feedback')->assertSessionMissing('success');

        $this->assertNull($checkout->refresh()->last_synced_at);
        $this->assertNull($invoice->refresh()->paid_at);
    }

    public function test_confirmed_pix_consultation_only_reports_success_after_actual_invoice_settlement(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))
            ->assertSessionHas('payment_feedback.level', 'success')->assertSessionHasNoErrors();

        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertTrue($subscription->refresh()->allowsAccess());
        $this->assertStringContainsString('mensalidade de '.$invoice->reference_month->format('m/Y').' quitada', session('payment_feedback.message'));
    }

    public function test_simulated_pix_consultation_never_reports_real_payment_success(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        $checkout->update(['environment' => 'test']);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))
            ->assertSessionHas('payment_feedback.level', 'info')->assertSessionMissing('success');

        $this->assertStringContainsString('É uma simulação', session('payment_feedback.message'));
        $this->assertNull($invoice->refresh()->paid_at);
    }

    public function test_closing_local_attempt_does_not_claim_or_audit_remote_cancellation(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        $checkout->update(['status' => 'creating', 'remote_id' => null, 'checkout_url' => null]);
        $checkout->forceFill(['created_at' => now()->subMinutes(11)])->save();
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval/search*' => Http::response(['results' => []])]);

        $this->actingAs($owner)->delete(route('subscription.checkouts.destroy', $checkout))->assertSessionHasNoErrors()
            ->assertSessionHas('payment_feedback.level', 'info')->assertSessionMissing('success');

        $this->assertStringContainsString('apenas no GiraComanda', session('payment_feedback.message'));
        $this->assertSame('cancelled', $checkout->refresh()->status);
        $this->assertNull($checkout->remote_id);
        $this->assertDatabaseMissing('platform_activity_logs', ['action' => 'subscription_checkout_cancelled']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() !== 'GET');
    }

    public function test_already_paid_pix_cannot_show_successful_cancellation(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $this->actingAs($owner)->delete(route('subscription.checkouts.destroy', $checkout))
            ->assertSessionHas('payment_feedback.level', 'warning')->assertSessionMissing('success');

        $this->assertStringContainsString('não foi cancelado', session('payment_feedback.message'));
        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertDatabaseMissing('platform_activity_logs', ['action' => 'subscription_checkout_cancelled']);
        Http::assertSentCount(1);
    }

    public function test_provider_error_details_redact_credentials_email_and_url_before_display_or_persistence(): void
    {
        $this->settings();
        [$owner] = $this->customer();
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/preapproval' => Http::response([
            'message' => 'Invalid payer_email buyer@example.com token test-token at https://private.example/secret',
        ], 400, ['x-request-id' => 'provider-reference-123'])]);

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), [
            'kind' => 'card', 'payer_email' => 'buyer@example.com', 'recurring_consent' => '1',
        ])->assertSessionHasErrors('payment');

        $message = SubscriptionCheckout::query()->sole()->failure_message;
        $this->assertStringContainsString('Invalid payer_email', $message);
        $this->assertStringContainsString('provider-reference-123', $message);
        $this->assertStringNotContainsString('buyer@example.com', $message);
        $this->assertStringNotContainsString('test-token', $message);
        $this->assertStringNotContainsString('private.example', $message);
    }

    public function test_rejected_creation_is_not_reported_as_a_successful_reconciliation(): void
    {
        $this->settings();
        [, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        $checkout->update(['status' => 'rejected', 'remote_id' => null]);
        Http::preventStrayRequests();

        $this->artisan('subscriptions:reconcile-mercado-pago')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($checkout->refresh()->last_synced_at);
    }

    public function test_refunded_simulation_cannot_keep_the_confirmed_payment_feedback(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        $checkout->update(['environment' => 'test']);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::sequence()
            ->push($this->paymentData($checkout))->push($this->paymentData($checkout, ['status' => 'refunded']))]);
        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))->assertSessionHas('payment_feedback.level', 'info');

        $this->put(route('subscription.checkouts.update', $checkout))->assertSessionHas('payment_feedback.level', 'warning');

        $this->assertStringNotContainsString('Pagamento de teste confirmado', session('payment_feedback.message'));
        $this->assertNull($invoice->refresh()->paid_at);
    }

    private function settings(array $attributes = []): PlatformPaymentSetting
    {
        return PlatformPaymentSetting::factory()->create([
            'enabled' => true, 'test_access_token' => 'test-token', 'test_webhook_secret' => 'test-secret', ...$attributes,
            'production_access_token' => 'production-fake-token', 'production_webhook_secret' => 'test-secret',
        ]);
    }

    public function test_card_subscription_accepts_account_money_payment(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval/rec-123' => Http::response($this->recurrenceData($checkout)),
            'api.mercadopago.com/authorized_payments/search*' => Http::response(['results' => [
                ['id' => 'auth-1', 'payment' => ['id' => 184493277959, 'status' => 'approved'], 'last_modified' => now()->toIso8601String()],
            ]]),
            'api.mercadopago.com/authorized_payments/auth-1' => Http::response([
                'id' => 'auth-1', 'preapproval_id' => 'rec-123', 'external_reference' => $checkout->uuid,
                'payment' => ['id' => 184493277959], 'debit_date' => today()->toIso8601String(),
            ]),
            'api.mercadopago.com/v1/payments/184493277959' => Http::response($this->paymentData($checkout, [
                'id' => 184493277959,
                'payment_type_id' => 'account_money',
                'payment_method_id' => 'account_money',
            ])),
        ]);

        $this->actingAs($owner)->put(route('subscription.checkouts.update', $checkout))
            ->assertRedirect()->assertSessionHas('payment_feedback');

        $this->assertNotNull($invoice->refresh()->paid_at);
        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertNull(SubscriptionGatewayPayment::query()->sole()->review_reason);
    }

    public function test_portal_synchronizes_automatically_when_redirected_with_preapproval_id(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->card($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval/rec-123' => Http::response($this->recurrenceData($checkout)),
            'api.mercadopago.com/authorized_payments/search*' => Http::response(['results' => []]),
        ]);

        $this->actingAs($owner)->get(route('subscription.show', ['preapproval_id' => 'rec-123']))
            ->assertOk()
            ->assertSessionHas('payment_feedback');

        $this->assertSame('authorized', $checkout->refresh()->status);
    }

    public function test_webhook_accepts_id_in_body_without_query_param(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $checkout = $this->pix($subscription, $invoice);
        Http::preventStrayRequests();
        Http::fake(['api.mercadopago.com/v1/payments/12345' => Http::response($this->paymentData($checkout))]);

        $timestamp = (string) now()->timestamp;
        $hash = hash_hmac('sha256', 'id:12345;request-id:test-req;ts:'.$timestamp.';', 'test-secret');

        $this->postJson(route('mercado-pago.webhook', ['environment' => 'production']),
            ['type' => 'payment', 'data' => ['id' => '12345']],
            ['x-request-id' => 'test-req', 'x-signature' => 'ts='.$timestamp.',v1='.$hash])
            ->assertOk()
            ->assertJson(['received' => true]);

        $this->assertNotNull($invoice->refresh()->paid_at);
    }

    public function test_owner_can_subscribe_yearly_and_directly_during_trial(): void
    {
        $this->settings();
        [$owner, $subscription] = $this->customer();
        $subscription->update(['trial_ends_at' => today()->addDays(5), 'status' => SubscriptionStatus::Trial]);
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'rec-annual-123',
                'status' => 'pending',
                'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=rec-annual-123',
            ], 201),
        ]);

        $this->actingAs($owner)->post(route('subscription.checkouts.store'), [
            'kind' => 'card',
            'billing_cycle' => 'yearly',
            'payer_email' => 'buyer@example.com',
            'recurring_consent' => '1',
        ])->assertRedirect('https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=rec-annual-123');

        $checkout = SubscriptionCheckout::query()->where('remote_id', 'rec-annual-123')->firstOrFail();
        $this->assertSame('yearly', $checkout->billing_cycle);
        $this->assertSame(297000, $checkout->amount_cents);

        Http::assertSent(function (ClientRequest $request) use ($checkout): bool {
            return (int) $request['auto_recurring']['frequency'] === 12
                && $request['auto_recurring']['frequency_type'] === 'months'
                && (int) $request['auto_recurring']['transaction_amount'] === 2970
                && $request['external_reference'] === $checkout->uuid;
        });
    }

    public function test_yearly_subscription_settles_and_extends_access_by_one_year(): void
    {
        $this->settings();
        [$owner, $subscription, $invoice] = $this->customer();
        $invoice->update(['amount_cents' => 297000]);
        $checkout = SubscriptionCheckout::factory()->create([
            'subscription_id' => $subscription->id,
            'subscription_invoice_id' => $invoice->id,
            'kind' => 'card',
            'billing_cycle' => 'yearly',
            'status' => 'authorized',
            'remote_id' => 'rec-annual-123',
            'environment' => 'production',
            'amount_cents' => 297000,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/preapproval/rec-annual-123' => Http::response([
                'id' => 'rec-annual-123',
                'external_reference' => $checkout->uuid,
                'status' => 'authorized',
                'auto_recurring' => ['frequency' => 12, 'frequency_type' => 'months', 'currency_id' => 'BRL', 'transaction_amount' => 2970],
            ]),
            'api.mercadopago.com/authorized_payments/auth-123' => Http::response([
                'id' => 'auth-123',
                'preapproval_id' => 'rec-annual-123',
                'payment' => ['id' => 99999],
                'external_reference' => $checkout->uuid,
            ]),
            'api.mercadopago.com/v1/payments/99999' => Http::response([
                'id' => 99999,
                'external_reference' => $checkout->uuid,
                'live_mode' => true,
                'status' => 'approved',
                'transaction_amount' => 2970,
                'currency_id' => 'BRL',
                'payment_type_id' => 'credit_card',
                'date_approved' => now()->toIso8601String(),
            ]),
        ]);

        app(SynchronizeMercadoPago::class)->authorizedPayment('production', 'auth-123');

        $subscription->refresh();
        $this->assertSame('yearly', $subscription->billing_cycle);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->paid_until->isFuture());
        $this->assertGreaterThan(300, now()->diffInDays($subscription->paid_until));
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

    private function pix(Subscription $subscription, SubscriptionInvoice $invoice): SubscriptionCheckout
    {
        return SubscriptionCheckout::factory()->create(['subscription_id' => $subscription->id, 'subscription_invoice_id' => $invoice->id, 'remote_id' => '12345', 'environment' => 'production']);
    }

    private function card(Subscription $subscription, SubscriptionInvoice $invoice): SubscriptionCheckout
    {
        return SubscriptionCheckout::factory()->create(['subscription_id' => $subscription->id, 'subscription_invoice_id' => $invoice->id,
            'kind' => 'card', 'status' => 'pending', 'remote_id' => 'rec-123', 'pix_code' => null, 'environment' => 'production',
            'checkout_url' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=rec-123']);
    }

    private function paymentData(SubscriptionCheckout $checkout, array $overrides = []): array
    {
        return ['id' => 12345, 'external_reference' => $checkout->uuid, 'live_mode' => $checkout->environment === 'production', 'status' => 'approved',
            'transaction_amount' => 297, 'currency_id' => 'BRL', 'payment_method_id' => 'pix',
            'date_approved' => now()->toIso8601String(), ...$overrides];
    }

    private function recurrenceData(SubscriptionCheckout $checkout, array $overrides = []): array
    {
        return ['id' => 'rec-123', 'external_reference' => $checkout->uuid, 'status' => 'authorized',
            'auto_recurring' => ['frequency' => 1, 'frequency_type' => 'months', 'currency_id' => 'BRL', 'transaction_amount' => 297], ...$overrides];
    }

    private function webhook(string $id, string $type = 'payment', string $environment = 'production'): TestResponse
    {
        $timestamp = (string) now()->timestamp;
        $hash = hash_hmac('sha256', 'id:'.strtolower($id).';request-id:test-request;ts:'.$timestamp.';', 'test-secret');

        return $this->postJson(route('mercado-pago.webhook', ['environment' => $environment]).'?data.id='.$id,
            ['type' => $type, 'data' => ['id' => $id]], ['x-request-id' => 'test-request', 'x-signature' => 'ts='.$timestamp.',v1='.$hash]);
    }
}
