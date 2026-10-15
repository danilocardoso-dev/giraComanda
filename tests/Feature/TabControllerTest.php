<?php

namespace Tests\Feature;

use App\CardStatus;
use App\Models\Card;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\EstablishmentSetting;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\TabItemShare;
use App\Models\User;
use App\PaymentMethod;
use App\ReceivableStatus;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TabControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendant_can_open_tab_with_available_card(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['status' => CardStatus::Available]);

        $response = $this->actingAs($attendant)->post(route('tabs.store'), ['card_id' => $card->id]);

        $tab = Tab::query()->sole();

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertSame($attendant->id, $tab->opened_by);
        $this->assertSame(TabStatus::Open, $tab->status);
        $this->assertSame(CardStatus::InUse, $card->refresh()->status);
    }

    public function test_card_in_use_cannot_open_another_tab(): void
    {
        $cashier = User::factory()->cashier()->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);

        $response = $this->actingAs($cashier)->post(route('tabs.store'), ['card_id' => $card->id]);

        $response->assertSessionHasErrors(['card_id' => 'O cartão selecionado não está disponível.']);
        $this->assertDatabaseCount('tabs', 0);
    }

    public function test_cashier_can_close_tab_in_customer_name_and_release_card(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create(['opened_by' => $cashier, 'total_cents' => 1000]);
        TabItem::factory()->for($tab)->create(['total_cents' => 1000]);

        $response = $this->actingAs($cashier)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Account->value,
            'customer_id' => $customer->id,
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertSame(TabStatus::Closed, $tab->refresh()->status);
        $this->assertSame($customer->id, $tab->customer_id);
        $this->assertSame($cashier->id, $tab->closed_by);
        $this->assertSame(CardStatus::Available, $card->refresh()->status);
        $this->assertDatabaseHas('receivables', [
            'customer_id' => $customer->id,
            'tab_id' => $tab->id,
            'amount_cents' => 1000,
            'status' => ReceivableStatus::Open->value,
        ]);
    }

    public function test_closing_tab_creates_receivables_for_item_shares_and_unshared_items(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        EstablishmentSetting::factory()->create(['general_due_day' => 10]);
        $cashier = User::factory()->cashier()->create();
        $closingCustomer = Customer::factory()->create(['due_day' => null]);
        $sharedCustomer = Customer::factory()->create(['due_day' => 25]);
        $tab = Tab::factory()->create(['opened_by' => $cashier, 'total_cents' => 2000]);
        $sharedItem = TabItem::factory()->for($tab)->create(['total_cents' => 1000]);
        TabItemShare::factory()->for($sharedItem, 'item')->for($closingCustomer)->create(['amount_cents' => 500]);
        TabItemShare::factory()->for($sharedItem, 'item')->for($sharedCustomer)->create(['amount_cents' => 500]);
        TabItem::factory()->for($tab)->create(['total_cents' => 1000]);

        $response = $this->actingAs($cashier)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Account->value,
            'customer_id' => $closingCustomer->id,
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertDatabaseHas('receivables', [
            'customer_id' => $closingCustomer->id,
            'tab_id' => $tab->id,
            'amount_cents' => 1500,
        ]);
        $this->assertDatabaseHas('receivables', [
            'customer_id' => $sharedCustomer->id,
            'tab_id' => $tab->id,
            'amount_cents' => 500,
        ]);
        $this->assertSame('2026-11-10', Receivable::query()->whereBelongsTo($closingCustomer)->sole()->due_date->toDateString());
        $this->assertSame('2026-10-25', Receivable::query()->whereBelongsTo($sharedCustomer)->sole()->due_date->toDateString());
        $this->assertSame(2000, Receivable::query()->sum('amount_cents'));
    }

    public function test_cashier_can_close_tab_with_immediate_payment_without_customer(): void
    {
        $this->travelTo('2026-10-08 18:30:00');
        $cashier = User::factory()->cashier()->create();
        $cashSession = CashSession::factory()->for($cashier, 'opener')->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create(['opened_by' => $cashier, 'total_cents' => 3500]);
        TabItem::factory()->for($tab)->create(['total_cents' => 3500]);

        $response = $this->actingAs($cashier)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Pix->value,
            'customer_id' => '',
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $payment = Payment::query()->sole();
        $this->assertSame(PaymentMethod::Pix, $payment->method);
        $this->assertSame(3500, $payment->amount_cents);
        $this->assertSame($cashier->id, $payment->recorded_by);
        $this->assertSame($cashSession->id, $payment->cash_session_id);
        $this->assertSame('2026-10-08 18:30:00', $payment->paid_at->format('Y-m-d H:i:s'));
        $this->assertNull($tab->refresh()->customer_id);
        $this->assertDatabaseCount('receivables', 0);
        $this->assertSame(CardStatus::Available, $card->refresh()->status);
    }

    public function test_cashier_can_close_tab_with_mixed_payment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $cashSession = CashSession::factory()->for($cashier, 'opener')->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create(['opened_by' => $cashier, 'total_cents' => 1500]);
        TabItem::factory()->for($tab)->create(['total_cents' => 1500]);

        $response = $this->actingAs($cashier)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Mixed->value,
            'payments' => [
                PaymentMethod::Cash->value => '5,00',
                PaymentMethod::Pix->value => '10,00',
            ],
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertDatabaseHas('payments', [
            'tab_id' => $tab->id,
            'cash_session_id' => $cashSession->id,
            'method' => PaymentMethod::Cash->value,
            'amount_cents' => 500,
        ]);
        $this->assertDatabaseHas('payments', [
            'tab_id' => $tab->id,
            'method' => PaymentMethod::Pix->value,
            'amount_cents' => 1000,
        ]);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(TabStatus::Closed, $tab->refresh()->status);
        $this->assertSame(CardStatus::Available, $card->refresh()->status);
    }

    public function test_immediate_payment_requires_open_cash_session(): void
    {
        $cashier = User::factory()->cashier()->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create(['opened_by' => $cashier, 'total_cents' => 1000]);
        TabItem::factory()->for($tab)->create(['total_cents' => 1000]);

        $response = $this->actingAs($cashier)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Cash->value,
        ]);

        $response->assertSessionHasErrors(['payment_method' => 'Abra o caixa antes de registrar recebimentos.']);
        $this->assertSame(TabStatus::Open, $tab->refresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_mixed_payment_must_match_tab_total(): void
    {
        $cashier = User::factory()->cashier()->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create(['opened_by' => $cashier, 'total_cents' => 1500]);
        TabItem::factory()->for($tab)->create(['total_cents' => 1500]);

        $response = $this->actingAs($cashier)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Mixed->value,
            'payments' => [
                PaymentMethod::Cash->value => '5,00',
                PaymentMethod::DebitCard->value => '5,00',
            ],
        ]);

        $response->assertSessionHasErrors(['payments' => 'A soma dos pagamentos deve ser igual ao total da comanda.']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(TabStatus::Open, $tab->refresh()->status);
        $this->assertSame(CardStatus::InUse, $card->refresh()->status);
    }

    public function test_account_closing_requires_customer(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create(['total_cents' => 1000]);

        $response = $this->actingAs($cashier)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Account->value,
            'customer_id' => '',
        ]);

        $response->assertSessionHasErrors(['customer_id' => 'Selecione o cliente que receberá a anotação.']);
        $this->assertSame(TabStatus::Open, $tab->refresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('receivables', 0);
    }

    public function test_attendant_cannot_close_tab(): void
    {
        $attendant = User::factory()->create();
        $customer = Customer::factory()->create();
        $tab = Tab::factory()->create(['total_cents' => 1000]);

        $response = $this->actingAs($attendant)->post(route('tabs.close', $tab), [
            'payment_method' => PaymentMethod::Account->value,
            'customer_id' => $customer->id,
        ]);

        $response->assertForbidden();
        $this->assertSame(TabStatus::Open, $tab->refresh()->status);
    }

    public function test_closed_tab_page_shows_receipt_link_to_cashier(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create([
            'opened_by' => $cashier,
            'closed_by' => $cashier,
            'status' => TabStatus::Closed,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($cashier)->get(route('tabs.show', $tab));

        $response->assertOk();
        $response->assertSee(route('tabs.receipt', $tab), false);
        $response->assertSee('Imprimir comprovante não fiscal');
    }
}
