<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\ProductSaleType;
use App\ReceivableStatus;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerSummaryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_can_view_customer_consumption_balance_and_available_credit(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create([
            'name' => 'Maria do Resumo',
            'credit_limit_cents' => 10000,
        ]);
        $openTab = Tab::factory()->create([
            'customer_id' => $customer->id,
            'status' => TabStatus::Closed,
            'total_cents' => 4000,
            'closed_at' => now(),
        ]);
        TabItem::factory()->for($openTab)->create([
            'product_name' => 'Prato Executivo',
            'sale_type' => ProductSaleType::Unit,
            'quantity_milliunits' => 1000,
            'unit_price_cents' => 4000,
            'total_cents' => 4000,
        ]);
        Receivable::factory()->for($customer)->for($openTab)->create([
            'amount_cents' => 4000,
            'paid_cents' => 1500,
            'status' => ReceivableStatus::Open,
        ]);
        $paidTab = Tab::factory()->create([
            'customer_id' => $customer->id,
            'status' => TabStatus::Closed,
            'total_cents' => 1000,
            'closed_at' => now()->subDay(),
        ]);
        TabItem::factory()->for($paidTab)->create([
            'product_name' => 'Suco Natural',
            'total_cents' => 1000,
        ]);
        Receivable::factory()->for($customer)->for($paidTab)->create([
            'amount_cents' => 1000,
            'paid_cents' => 1000,
            'status' => ReceivableStatus::Paid,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($cashier)->get(route('customers.summary', $customer));

        $response->assertOk();
        $response->assertSee('Maria do Resumo');
        $response->assertSee('Total anotado');
        $response->assertSee('R$ 50,00');
        $response->assertSee('Total pago');
        $response->assertSee('R$ 25,00');
        $response->assertSee('Falta receber');
        $response->assertSee('Limite disponível');
        $response->assertSee('R$ 75,00');
        $response->assertSee('Prato Executivo');
        $response->assertSee('Suco Natural');
        $response->assertSee('2 anotações');
    }

    public function test_attendant_cannot_view_customer_summary(): void
    {
        $attendant = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($attendant)->get(route('customers.summary', $customer));

        $response->assertForbidden();
    }

    public function test_customer_from_another_company_returns_404(): void
    {
        $cashier = User::factory()->cashier()->create();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();
        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
        ]);

        $response = $this->actingAs($cashier)->get(route('customers.summary', $otherCustomer));

        $response->assertNotFound();
    }
}
