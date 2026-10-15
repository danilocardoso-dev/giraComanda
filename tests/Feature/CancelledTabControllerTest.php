<?php

namespace Tests\Feature;

use App\CashSessionStatus;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\PaymentMethod;
use App\ReceivableStatus;
use App\StockMovementType;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CancelledTabControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_cancel_closed_tab_and_reverse_payment_and_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $cashSession = CashSession::factory()->for($admin, 'opener')->create();
        $tab = Tab::factory()->create([
            'closed_by' => $admin,
            'status' => TabStatus::Closed,
            'total_cents' => 2000,
            'closed_at' => now(),
        ]);
        $product = Product::factory()->create(['stock_milliunits' => 3000]);
        TabItem::factory()->for($tab)->for($product)->create([
            'quantity_milliunits' => 2000,
            'total_cents' => 2000,
        ]);
        Payment::factory()->for($tab)->for($cashSession)->create([
            'method' => PaymentMethod::Cash,
            'amount_cents' => 2000,
        ]);

        $response = $this->actingAs($admin)->post(route('tabs.cancellations.store', $tab), [
            'reason' => 'Pagamento lançado na forma incorreta.',
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $tab->refresh();
        $this->assertSame(TabStatus::Cancelled, $tab->status);
        $this->assertSame($admin->id, $tab->cancelled_by);
        $this->assertSame('Pagamento lançado na forma incorreta.', $tab->cancellation_reason);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(5000, $product->refresh()->stock_milliunits);
        $this->assertDatabaseHas('stock_movements', [
            'tab_id' => $tab->id,
            'type' => StockMovementType::SaleReversal->value,
            'quantity_delta_milliunits' => 2000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $tab->id,
            'description' => 'Comanda cancelada: Pagamento lançado na forma incorreta.',
        ]);
    }

    public function test_cashier_cannot_cancel_closed_tab(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create([
            'closed_by' => $cashier,
            'status' => TabStatus::Closed,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($cashier)->post(route('tabs.cancellations.store', $tab), [
            'reason' => 'Lançamento incorreto.',
        ]);

        $response->assertForbidden();
        $this->assertSame(TabStatus::Closed, $tab->refresh()->status);
    }

    public function test_tab_from_closed_cash_session_cannot_be_cancelled(): void
    {
        $admin = User::factory()->admin()->create();
        $cashSession = CashSession::factory()->for($admin, 'opener')->closed()->create();
        $tab = Tab::factory()->create([
            'closed_by' => $admin,
            'status' => TabStatus::Closed,
            'closed_at' => now(),
        ]);
        Payment::factory()->for($tab)->for($cashSession)->create();

        $response = $this->actingAs($admin)->post(route('tabs.cancellations.store', $tab), [
            'reason' => 'Pagamento lançado incorretamente.',
        ]);

        $response->assertSessionHasErrors(['tab' => 'O caixa desta venda já foi fechado. Registre a correção no próximo caixa com orientação do administrador.']);
        $this->assertSame(TabStatus::Closed, $tab->refresh()->status);
        $this->assertSame(CashSessionStatus::Closed, $cashSession->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_paid_receivable_cannot_be_cancelled(): void
    {
        $admin = User::factory()->admin()->create();
        $tab = Tab::factory()->create([
            'closed_by' => $admin,
            'status' => TabStatus::Closed,
            'closed_at' => now(),
        ]);
        Receivable::factory()->for($tab)->create([
            'amount_cents' => 1000,
            'paid_cents' => 500,
            'status' => ReceivableStatus::Open,
        ]);

        $response = $this->actingAs($admin)->post(route('tabs.cancellations.store', $tab), [
            'reason' => 'Anotação lançada incorretamente.',
        ]);

        $response->assertSessionHasErrors(['tab' => 'Esta anotação já recebeu pagamento e não pode ser cancelada automaticamente.']);
        $this->assertSame(TabStatus::Closed, $tab->refresh()->status);
        $this->assertDatabaseCount('receivables', 1);
    }

    public function test_cancellation_requires_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $tab = Tab::factory()->create([
            'closed_by' => $admin,
            'status' => TabStatus::Closed,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('tabs.cancellations.store', $tab), ['reason' => '']);

        $response->assertSessionHasErrors(['reason' => 'Informe o motivo do cancelamento.']);
        $this->assertSame(TabStatus::Closed, $tab->refresh()->status);
    }
}
