<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformSubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_admin_can_update_subscription_terms(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00'));
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create(['name' => 'Restaurante Assinante']);
        $subscription = Subscription::factory()->for($company)->create();

        $response = $this->actingAs($platformAdmin)->put(route('platform.subscriptions.update', $company), [
            'status' => SubscriptionStatus::Active->value,
            'monthly_amount' => '249,90',
            'billing_day' => 15,
            'billing_phone' => '(11) 99999-8888',
            'started_at' => '2026-10-01',
            'trial_ends_at' => null,
            'notes' => 'Plano piloto.',
        ]);

        $response->assertRedirect();
        $this->assertSame(24990, $subscription->refresh()->monthly_amount_cents);
        $this->assertSame(15, $subscription->billing_day);
        $this->assertSame('11999998888', $subscription->billing_phone);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertDatabaseHas('platform_activity_logs', [
            'company_id' => $company->id,
            'platform_admin_user_id' => $platformAdmin->id,
            'action' => 'subscription_updated',
        ]);
    }

    public function test_opening_company_page_generates_overdue_invoice_and_whatsapp_collection(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00'));
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create(['name' => 'Cantina Validação']);
        Branch::factory()->for($company)->create();
        $subscription = Subscription::factory()->for($company)->create([
            'status' => SubscriptionStatus::Active,
            'monthly_amount_cents' => 90000,
            'billing_day' => 10,
            'billing_phone' => '11999999999',
            'started_at' => '2026-10-09',
        ]);

        $response = $this->actingAs($platformAdmin)->get(route('platform.companies.show', $company));

        $response->assertOk();
        $response->assertSee('R$ 900,00');
        $response->assertSee('Vencimento em atraso');
        $response->assertSee('Cobrar no WhatsApp');
        $response->assertSee('https://wa.me/5511999999999', false);
        $invoice = $subscription->invoices()->sole();
        $this->assertSame('2026-10-01', $invoice->reference_month->toDateString());
        $this->assertSame('2026-10-10', $invoice->due_date->toDateString());
        $this->assertSame(90000, $invoice->amount_cents);
        $this->assertTrue($invoice->generated_automatically);

        $this->actingAs($platformAdmin)->get(route('platform.companies.show', $company))->assertOk();
        $this->assertSame(1, $subscription->invoices()->count());
    }

    public function test_dashboard_synchronizes_missing_invoices_before_calculating_overdue_totals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00'));
        $platformAdmin = User::factory()->platformAdmin()->create();
        Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'monthly_amount_cents' => 90000,
            'billing_day' => 10,
            'started_at' => '2026-10-09',
        ]);

        $response = $this->actingAs($platformAdmin)->get(route('platform.dashboard'));

        $response->assertOk();
        $response->assertViewHas('overdueCompaniesCount', 1);
        $response->assertViewHas('overdueAmountCents', 90000);
        $response->assertViewHas('dueThisMonthCents', 0);
    }

    public function test_platform_admin_can_open_complete_company_management_page(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create(['name' => 'Cliente Piloto']);
        Branch::factory()->for($company)->create();
        Subscription::factory()->for($company)->create(['monthly_amount_cents' => 21990]);

        $response = $this->actingAs($platformAdmin)->get(route('platform.companies.show', $company));

        $response->assertOk();
        $response->assertSee('Cliente Piloto');
        $response->assertSee('Assinatura do software');
        $response->assertSee('R$ 219,90');
        $response->assertSee('Acessar como suporte');
    }

    public function test_platform_admin_can_create_and_receive_a_monthly_invoice(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create();
        $subscription = Subscription::factory()->for($company)->create();

        $createResponse = $this->actingAs($platformAdmin)->post(route('platform.invoices.store', $company), [
            'reference_month' => '2026-10',
            'due_date' => '2026-10-10',
            'amount' => '199,90',
        ]);

        $createResponse->assertRedirect();
        $invoice = SubscriptionInvoice::query()->where('subscription_id', $subscription->id)->sole();
        $this->assertSame(19990, $invoice->amount_cents);
        $this->assertSame('2026-10-01', $invoice->reference_month->toDateString());

        $paymentResponse = $this->actingAs($platformAdmin)->post(route('platform.invoices.payments.store', [$company, $invoice]), [
            'paid_on' => '2026-10-12',
            'payment_method' => 'pix',
        ]);

        $paymentResponse->assertRedirect();
        $this->assertSame('pix', $invoice->refresh()->payment_method);
        $this->assertSame('2026-10-12', $invoice->paid_at->toDateString());
        $this->assertSame(2, PlatformActivityLog::query()->where('company_id', $company->id)->count());
    }

    public function test_overdue_invoices_appear_on_platform_dashboard(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $subscription = Subscription::factory()->create(['monthly_amount_cents' => 18000]);
        SubscriptionInvoice::factory()->for($subscription)->create([
            'due_date' => today()->subDay(),
            'amount_cents' => 18000,
        ]);

        $response = $this->actingAs($platformAdmin)->get(route('platform.dashboard'));

        $response->assertOk();
        $response->assertViewHas('overdueCompaniesCount', 1);
        $response->assertViewHas('overdueAmountCents', 18000);
        $response->assertSee('R$ 180,00');
    }

    public function test_company_admin_cannot_change_platform_subscription(): void
    {
        $companyAdmin = User::factory()->admin()->create();
        $company = Company::factory()->create();
        Subscription::factory()->for($company)->create();

        $response = $this->actingAs($companyAdmin)->put(route('platform.subscriptions.update', $company), []);

        $response->assertForbidden();
    }

    public function test_suspended_subscription_blocks_regular_establishment_access(): void
    {
        $cashier = User::factory()->cashier()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $company->subscription()->update(['status' => SubscriptionStatus::Suspended]);

        $response = $this->actingAs($cashier)->get(route('dashboard'));

        $response->assertForbidden();
    }
}
