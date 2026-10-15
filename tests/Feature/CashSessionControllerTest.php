<?php

namespace Tests\Feature;

use App\CashMovementType;
use App\CashSessionStatus;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_can_open_cash_session_with_initial_amount(): void
    {
        $this->travelTo('2026-10-09 08:00:00');
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->post(route('cash.sessions.store'), [
            'opening_amount' => '150,50',
        ]);

        $response->assertRedirect(route('cash.index'));
        $cashSession = CashSession::query()->sole();
        $this->assertSame($cashier->id, $cashSession->opened_by);
        $this->assertSame(CashSessionStatus::Open, $cashSession->status);
        $this->assertSame(15050, $cashSession->opening_amount_cents);
        $this->assertSame('2026-10-09 08:00:00', $cashSession->opened_at->format('Y-m-d H:i:s'));
    }

    public function test_cannot_open_second_cash_session(): void
    {
        $cashier = User::factory()->cashier()->create();
        CashSession::factory()->for($cashier, 'opener')->create();

        $response = $this->actingAs($cashier)->post(route('cash.sessions.store'), [
            'opening_amount' => '100,00',
        ]);

        $response->assertSessionHasErrors(['opening_amount' => 'Já existe um caixa aberto. Feche-o antes de abrir outro.']);
        $this->assertDatabaseCount('cash_sessions', 1);
    }

    public function test_attendant_cannot_manage_cash_session(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->post(route('cash.sessions.store'), [
            'opening_amount' => '100,00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('cash_sessions', 0);
    }

    public function test_cashier_can_record_supply_and_withdrawal(): void
    {
        $cashier = User::factory()->cashier()->create();
        $cashSession = CashSession::factory()->for($cashier, 'opener')->create();

        $supplyResponse = $this->actingAs($cashier)->post(route('cash.movements.store', $cashSession), [
            'type' => CashMovementType::Supply->value,
            'amount' => '20,00',
            'description' => 'Troco adicional',
        ]);
        $withdrawalResponse = $this->actingAs($cashier)->post(route('cash.movements.store', $cashSession), [
            'type' => CashMovementType::Withdrawal->value,
            'amount' => '5,00',
            'description' => 'Compra emergencial',
        ]);

        $supplyResponse->assertRedirect(route('cash.index'));
        $withdrawalResponse->assertRedirect(route('cash.index'));
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $cashSession->id,
            'recorded_by' => $cashier->id,
            'type' => CashMovementType::Supply->value,
            'amount_cents' => 2000,
            'description' => 'Troco adicional',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $cashSession->id,
            'type' => CashMovementType::Withdrawal->value,
            'amount_cents' => 500,
        ]);
    }

    public function test_closing_cash_session_calculates_expected_amount_and_difference(): void
    {
        $this->travelTo('2026-10-09 18:00:00');
        $cashier = User::factory()->cashier()->create();
        $cashSession = CashSession::factory()->for($cashier, 'opener')->create([
            'opening_amount_cents' => 10000,
            'opened_at' => '2026-10-09 08:00:00',
        ]);
        Payment::factory()->for($cashSession)->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::Cash,
            'amount_cents' => 2500,
        ]);
        Payment::factory()->for($cashSession)->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::Pix,
            'amount_cents' => 5000,
        ]);
        $this->actingAs($cashier)->post(route('cash.movements.store', $cashSession), [
            'type' => CashMovementType::Supply->value,
            'amount' => '10,00',
            'description' => 'Troco',
        ]);
        $this->actingAs($cashier)->post(route('cash.movements.store', $cashSession), [
            'type' => CashMovementType::Withdrawal->value,
            'amount' => '5,00',
            'description' => 'Retirada',
        ]);

        $response = $this->actingAs($cashier)->post(route('cash.sessions.close', $cashSession), [
            'counted_amount' => '129,00',
            'notes' => 'Faltou um real.',
        ]);

        $response->assertRedirect(route('cash.index'));
        $cashSession->refresh();
        $this->assertSame(CashSessionStatus::Closed, $cashSession->status);
        $this->assertSame(13000, $cashSession->expected_amount_cents);
        $this->assertSame(12900, $cashSession->counted_amount_cents);
        $this->assertSame(-100, $cashSession->difference_cents);
        $this->assertSame($cashier->id, $cashSession->closed_by);
        $this->assertSame('Faltou um real.', $cashSession->notes);
        $this->assertSame('2026-10-09 18:00:00', $cashSession->closed_at->format('Y-m-d H:i:s'));
    }

    public function test_closed_cash_session_rejects_new_movements(): void
    {
        $cashier = User::factory()->cashier()->create();
        $cashSession = CashSession::factory()->closed()->for($cashier, 'opener')->create();

        $response = $this->actingAs($cashier)->post(route('cash.movements.store', $cashSession), [
            'type' => CashMovementType::Supply->value,
            'amount' => '10,00',
            'description' => 'Movimento indevido',
        ]);

        $response->assertSessionHasErrors(['cash_session' => 'Este caixa já foi fechado.']);
        $this->assertDatabaseCount('cash_movements', 0);
    }
}
