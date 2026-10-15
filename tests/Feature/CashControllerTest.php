<?php

namespace Tests\Feature;

use App\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendant_cannot_view_cash_movements(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('cash.index'));

        $response->assertForbidden();
    }

    public function test_cashier_can_view_payments_for_selected_date_grouped_by_method(): void
    {
        $cashier = User::factory()->cashier()->create();
        Payment::factory()->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::Pix,
            'amount_cents' => 1500,
            'paid_at' => '2026-10-08 12:00:00',
        ]);
        Payment::factory()->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::DebitCard,
            'amount_cents' => 2500,
            'paid_at' => '2026-10-08 13:00:00',
        ]);
        Payment::factory()->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::Cash,
            'amount_cents' => 9000,
            'paid_at' => '2026-10-07 13:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('cash.index', ['date' => '2026-10-08']));

        $response->assertOk();
        $response->assertSee('R$ 40,00');
        $response->assertSee('R$ 15,00');
        $response->assertSee('R$ 25,00');
        $response->assertDontSee('R$ 90,00');
    }

    public function test_cashier_can_view_receivable_payment_without_tab(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente Anotação']);
        Payment::factory()->for($cashier, 'recorder')->for($customer)->create([
            'tab_id' => null,
            'amount_cents' => 1800,
            'paid_at' => '2026-10-09 10:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('cash.index', ['date' => '2026-10-09']));

        $response->assertOk();
        $response->assertSee('Anotação paga');
        $response->assertSee('Cliente Anotação');
        $response->assertSee('R$ 18,00');
        $response->assertSee('Pagamento concluído e já baixado');
        $response->assertDontSee(route('receivables.show', $customer), false);
    }

    public function test_cashier_can_view_open_cash_session_summary(): void
    {
        $cashier = User::factory()->cashier()->create(['name' => 'Caixa da Manhã']);
        $cashSession = CashSession::factory()->for($cashier, 'opener')->create([
            'opening_amount_cents' => 10000,
        ]);
        Payment::factory()->for($cashSession)->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::Cash,
            'amount_cents' => 1500,
        ]);
        CashMovement::factory()->for($cashSession)->for($cashier, 'recorder')->create([
            'type' => CashMovementType::Supply,
            'amount_cents' => 1000,
        ]);
        CashMovement::factory()->for($cashSession)->for($cashier, 'recorder')->create([
            'type' => CashMovementType::Withdrawal,
            'amount_cents' => 500,
        ]);

        $response = $this->actingAs($cashier)->get(route('cash.index'));

        $response->assertOk();
        $response->assertSee('Caixa aberto');
        $response->assertSee('Caixa da Manhã');
        $response->assertSee('R$ 120,00');
    }

    public function test_cashier_can_view_items_from_a_tab_payment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create();
        TabItem::factory()->for($tab)->create([
            'product_name' => 'Prato executivo',
            'quantity_milliunits' => 1000,
            'unit_price_cents' => 2890,
            'total_cents' => 2890,
        ]);
        $payment = Payment::factory()->for($tab)->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::CreditCard,
            'amount_cents' => 2890,
        ]);

        $indexResponse = $this->actingAs($cashier)->get(route('cash.index'));
        $detailResponse = $this->get(route('cash.payments.show', $payment));

        $indexResponse->assertOk();
        $indexResponse->assertSee(route('cash.payments.show', $payment), false);
        $indexResponse->assertSee('Ver itens');
        $detailResponse->assertOk();
        $detailResponse->assertSee('Venda de comanda');
        $detailResponse->assertSee('Prato executivo');
        $detailResponse->assertSee('Cartão de crédito');
        $detailResponse->assertSee('R$ 28,90');
    }

    public function test_cashier_can_view_items_paid_from_customer_annotations(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente da Anotação']);
        $tab = Tab::factory()->for($customer)->create();
        TabItem::factory()->for($tab)->create([
            'product_name' => 'Refrigerante rateado',
            'quantity_milliunits' => 1000,
            'unit_price_cents' => 1000,
            'total_cents' => 1000,
        ]);
        $receivable = Receivable::factory()->for($customer)->for($tab)->create([
            'amount_cents' => 1000,
            'paid_cents' => 1000,
        ]);
        $payment = Payment::factory()->for($cashier, 'recorder')->for($customer)->create([
            'tab_id' => null,
            'method' => PaymentMethod::Cash,
            'amount_cents' => 1000,
        ]);
        PaymentAllocation::factory()->for($payment)->for($receivable)->create([
            'amount_cents' => 1000,
        ]);

        $response = $this->actingAs($cashier)->get(route('cash.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('Pagamento de anotação');
        $response->assertSee('Anotação do cliente');
        $response->assertSee('Recebido por');
        $response->assertSee('Dinheiro');
        $response->assertSee('Refrigerante rateado');
        $response->assertSee('Baixado neste pagamento');
        $response->assertSee('R$ 10,00');
    }
}
