<?php

namespace Tests\Feature;

use App\AuditArea;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MonthlyStatementDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MonthlyStatementDeliveryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_can_mark_monthly_statement_as_sent(): void
    {
        $this->travelTo('2026-10-31 18:00:00');
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create([
            'name' => 'Cliente Mensal',
            'phone' => '11999999999',
        ]);

        $response = $this->actingAs($cashier)->post(route('monthly-closing.sent.store', $customer), [
            'month' => '2026-10',
        ]);

        $response->assertRedirect(route('monthly-closing.index', ['month' => '2026-10']));
        $response->assertSessionHas('success', 'Resumo de Cliente Mensal marcado como enviado.');
        $this->assertDatabaseHas('monthly_statement_deliveries', [
            'customer_id' => $customer->id,
            'sent_by' => $cashier->id,
            'sent_at' => '2026-10-31 18:00:00',
        ]);
        $this->assertSame('2026-10-01', MonthlyStatementDelivery::query()->sole()->statement_month->toDateString());
        $auditLog = AuditLog::query()->where('area', AuditArea::Receivables)->latest('id')->firstOrFail();
        $this->assertSame('Resumo mensal marcado como enviado', $auditLog->description);
    }

    public function test_marking_same_month_again_updates_single_record(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['phone' => '11999999999']);
        MonthlyStatementDelivery::factory()->for($customer)->for($cashier, 'sender')->create([
            'statement_month' => '2026-10-01',
        ]);

        $response = $this->actingAs($cashier)->post(route('monthly-closing.sent.store', $customer), [
            'month' => '2026-10',
        ]);

        $response->assertRedirect(route('monthly-closing.index', ['month' => '2026-10']));
        $this->assertDatabaseCount('monthly_statement_deliveries', 1);
    }

    public function test_shared_customer_delivery_can_be_updated_from_another_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $originBranch = Branch::query()->where('slug', 'matriz')->sole();
        $sendingBranch = Branch::factory()->for($company)->create(['name' => 'Centro']);
        $company->update(['customer_sharing_enabled' => true]);
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $originBranch->id,
            'phone' => '11999999999',
        ]);
        MonthlyStatementDelivery::factory()->for($customer)->for($admin, 'sender')->create([
            'branch_id' => $originBranch->id,
            'statement_month' => '2026-10-01',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $sendingBranch->id])
            ->post(route('monthly-closing.sent.store', $customer), [
                'month' => '2026-10',
            ]);

        $response->assertRedirect(route('monthly-closing.index', ['month' => '2026-10']));
        $this->assertDatabaseCount('monthly_statement_deliveries', 1);
        $this->assertDatabaseHas('monthly_statement_deliveries', [
            'customer_id' => $customer->id,
            'branch_id' => $sendingBranch->id,
            'sent_by' => $admin->id,
        ]);
    }

    public function test_customer_without_phone_cannot_be_marked_as_sent(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['phone' => null]);

        $response = $this->actingAs($cashier)->from(route('monthly-closing.index'))->post(route('monthly-closing.sent.store', $customer), [
            'month' => '2026-10',
        ]);

        $response->assertRedirect(route('monthly-closing.index'));
        $response->assertSessionHas('error', 'Cadastre o telefone do cliente antes de marcar o resumo como enviado.');
        $this->assertDatabaseCount('monthly_statement_deliveries', 0);
    }

    public function test_attendant_cannot_mark_statement_as_sent(): void
    {
        $attendant = User::factory()->create();
        $customer = Customer::factory()->create(['phone' => '11999999999']);

        $response = $this->actingAs($attendant)->post(route('monthly-closing.sent.store', $customer), [
            'month' => '2026-10',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('monthly_statement_deliveries', 0);
    }
}
