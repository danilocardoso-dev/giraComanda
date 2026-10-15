<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receivable;
use App\Models\TabItem;
use App\Models\User;
use App\PaymentMethod;
use App\ReceivableStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrossBranchReceivableTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shared_customer_debt_can_be_paid_in_another_branch_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $originBranch = Branch::query()->where('slug', 'matriz')->sole();
        $receivingBranch = Branch::factory()->for($company)->create(['name' => 'Centro']);
        $company->update(['customer_sharing_enabled' => true]);
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $originBranch->id,
            'name' => 'Cliente Compartilhado',
        ]);
        $receivable = Receivable::factory()->for($customer)->create([
            'branch_id' => $originBranch->id,
            'amount_cents' => 2500,
        ]);
        $receivable->tab->update(['customer_id' => $customer->id]);
        TabItem::factory()->for($receivable->tab)->create([
            'product_name' => 'Produto da filial de origem',
            'total_cents' => 2500,
        ]);
        $cashSession = CashSession::factory()->for($admin, 'opener')->create([
            'branch_id' => $receivingBranch->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $receivingBranch->id])
            ->post(route('receivables.payments.store', $customer), [
                'method' => PaymentMethod::Pix->value,
                'amount' => '25,00',
            ]);

        $response->assertRedirect();
        $payment = Payment::withoutGlobalScope('currentBranch')->sole();
        $this->assertSame($receivingBranch->id, $payment->branch_id);
        $this->assertSame($cashSession->id, $payment->cash_session_id);
        $this->assertSame($customer->id, $payment->customer_id);
        $this->assertSame(2500, $payment->amount_cents);
        $this->assertSame(ReceivableStatus::Paid, $receivable->refresh()->status);
        $this->assertSame(2500, $receivable->paid_cents);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'receivable_id' => $receivable->id,
            'amount_cents' => 2500,
        ]);
        $this->assertSame(2500, PaymentAllocation::query()->sum('amount_cents'));

        $detailResponse = $this->get(route('cash.payments.show', $payment));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Produto da filial de origem');
        $detailResponse->assertSee('R$ 25,00');
    }

    public function test_shared_customer_statement_includes_charges_from_another_company_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $originBranch = Branch::query()->where('slug', 'matriz')->sole();
        $viewingBranch = Branch::factory()->for($company)->create(['name' => 'Centro']);
        $company->update(['customer_sharing_enabled' => true]);
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $originBranch->id,
            'name' => 'Cliente com saldo compartilhado',
        ]);
        Receivable::factory()->for($customer)->create([
            'branch_id' => $originBranch->id,
            'amount_cents' => 4250,
            'created_at' => '2026-10-08 12:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $viewingBranch->id])
            ->get(route('receivables.show', ['customer' => $customer, 'month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('Cliente com saldo compartilhado');
        $response->assertSee('R$ 42,50');
    }

    public function test_customer_debt_from_another_branch_stays_hidden_when_sharing_is_disabled(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $originBranch = Branch::query()->where('slug', 'matriz')->sole();
        $viewingBranch = Branch::factory()->for($company)->create();
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $originBranch->id,
        ]);
        Receivable::factory()->for($customer)->create([
            'branch_id' => $originBranch->id,
            'amount_cents' => 4250,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $viewingBranch->id])
            ->get(route('receivables.show', $customer));

        $response->assertNotFound();
    }
}
