<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\TabItemShare;
use App\Models\User;
use App\PaymentMethod;
use App\ProductSaleType;
use App\ReceivableStatus;
use App\TabStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReceivableControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendant_cannot_access_receivables(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('receivables.index'));

        $response->assertForbidden();
    }

    public function test_cashier_can_view_open_balance_grouped_by_customer(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Maria Souza']);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 1250,
            'paid_cents' => 250,
        ]);
        Receivable::factory()->for($customer)->create(['amount_cents' => 750]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 500,
            'status' => ReceivableStatus::Paid,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($cashier)->get(route('receivables.index'));

        $response->assertOk();
        $response->assertSee('Maria Souza');
        $response->assertSee('R$ 17,50');
        $response->assertSee('Total que falta receber');
        $response->assertSee('Valor atrasado');
        $response->assertSee('Ainda está no prazo');
    }

    public function test_open_receivables_list_shows_the_items_and_customer_share(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente do Rateio']);
        $otherCustomer = Customer::factory()->create();
        $tab = Tab::factory()->create([
            'customer_id' => $customer->id,
            'status' => TabStatus::Closed,
            'total_cents' => 1000,
            'closed_at' => now(),
        ]);
        $item = TabItem::factory()->for($tab)->create([
            'product_name' => 'Coca-Cola 2L',
            'sale_type' => ProductSaleType::Unit,
            'quantity_milliunits' => 1000,
            'unit_price_cents' => 1000,
            'total_cents' => 1000,
        ]);
        TabItemShare::factory()->for($item, 'item')->for($customer)->create(['amount_cents' => 250]);
        TabItemShare::factory()->for($item, 'item')->for($otherCustomer)->create(['amount_cents' => 750]);
        Receivable::factory()->for($customer)->for($tab)->create(['amount_cents' => 250]);

        $response = $this->actingAs($cashier)->get(route('receivables.index'));

        $response->assertOk();
        $response->assertSee('Coca-Cola 2L');
        $response->assertSee('Rateio entre 2 clientes');
        $response->assertSee('Sua parte');
        $response->assertSee('R$ 2,50');
    }

    public function test_receivable_summary_explains_overdue_and_not_due_amounts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00'));
        $cashier = User::factory()->cashier()->create();
        $overdueCustomer = Customer::factory()->create(['name' => 'Cliente Atrasado']);
        $notDueCustomer = Customer::factory()->create(['name' => 'Cliente no Prazo']);
        Receivable::factory()->for($overdueCustomer)->create([
            'amount_cents' => 750,
            'due_date' => '2026-10-10',
        ]);
        Receivable::factory()->for($notDueCustomer)->create([
            'amount_cents' => 250,
            'due_date' => '2026-10-15',
        ]);

        $response = $this->actingAs($cashier)->get(route('receivables.index'));

        $response->assertOk();
        $response->assertViewHas('openTotalCents', 1000);
        $response->assertViewHas('overdueTotalCents', 750);
        $response->assertViewHas('notDueTotalCents', 250);
        $response->assertSee('R$ 7,50 atrasados');
        $response->assertSee('R$ 2,50 no prazo');
        $response->assertSee('R$ 10,00 para receber');
        $response->assertSee('Venceu em 10/10/2026');
        $response->assertSee('Vence em 15/10/2026');
    }

    public function test_customer_statement_only_lists_selected_month(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $selectedMonthReceivable = Receivable::factory()->for($customer)->create([
            'amount_cents' => 1500,
            'created_at' => '2026-10-08 12:00:00',
        ]);
        $previousMonthReceivable = Receivable::factory()->for($customer)->create([
            'amount_cents' => 9000,
            'created_at' => '2026-09-08 12:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('receivables.show', [
            'customer' => $customer,
            'month' => '2026-10',
        ]));

        $response->assertOk();
        $response->assertSee($selectedMonthReceivable->tab->card->code);
        $response->assertDontSee($previousMonthReceivable->tab->card->code);
        $response->assertSee('Outubro de 2026');
        $response->assertSee('<select id="month" name="month"', false);
    }

    public function test_customer_statement_lists_the_items_that_generated_the_annotation(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $tab = Tab::factory()->create([
            'customer_id' => $customer->id,
            'status' => TabStatus::Closed,
            'total_cents' => 1500,
            'closed_at' => now(),
        ]);
        TabItem::factory()->for($tab)->create([
            'product_name' => 'Suco de Laranja',
            'sale_type' => ProductSaleType::Unit,
            'quantity_milliunits' => 2000,
            'unit_price_cents' => 750,
            'total_cents' => 1500,
        ]);
        Receivable::factory()->for($customer)->for($tab)->create([
            'amount_cents' => 1500,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($cashier)->get(route('receivables.show', $customer));

        $response->assertOk();
        $response->assertSee('Itens desta anotação');
        $response->assertSee('Suco de Laranja');
        $response->assertSee('2 un.');
        $response->assertSee('R$ 15,00');
    }

    public function test_customer_statement_calculates_monthly_balances_and_builds_whatsapp_message(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create([
            'name' => 'Maria do Resumo',
            'phone' => '11999999999',
        ]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 10000,
            'created_at' => '2026-09-10 12:00:00',
        ]);
        Payment::factory()->for($customer)->for($cashier, 'recorder')->create([
            'tab_id' => null,
            'method' => PaymentMethod::Pix,
            'amount_cents' => 2000,
            'paid_at' => '2026-09-20 12:00:00',
        ]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 5000,
            'created_at' => '2026-10-08 12:00:00',
        ]);
        Payment::factory()->for($customer)->for($cashier, 'recorder')->create([
            'tab_id' => null,
            'method' => PaymentMethod::Cash,
            'amount_cents' => 3000,
            'paid_at' => '2026-10-15 12:00:00',
        ]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 9900,
            'created_at' => '2026-11-08 12:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('receivables.show', [
            'customer' => $customer,
            'month' => '2026-10',
        ]));

        $response->assertOk();
        $response->assertSee('Saldo anterior');
        $response->assertSee('R$ 80,00');
        $response->assertSee('R$ 50,00');
        $response->assertSee('R$ 30,00');
        $response->assertSee('R$ 100,00');
        $response->assertSee('https://wa.me/5511999999999', false);
        $response->assertSee('Enviar no WhatsApp');
    }
}
