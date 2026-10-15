<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receivable;
use App\Models\User;
use App\PaymentMethod;
use App\ReceivableStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReceivablePaymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendant_cannot_register_receivable_payment(): void
    {
        $attendant = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($attendant)->post(route('receivables.payments.store', $customer), [
            'method' => PaymentMethod::Pix->value,
            'amount' => '10,00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cashier_can_register_partial_payment_allocated_to_oldest_receivables(): void
    {
        $this->travelTo('2026-10-09 09:30:00');
        $cashier = User::factory()->cashier()->create();
        $cashSession = CashSession::factory()->for($cashier, 'opener')->create();
        $customer = Customer::factory()->create();
        $oldest = Receivable::factory()->for($customer)->create([
            'amount_cents' => 1000,
            'due_date' => '2026-10-10',
        ]);
        $newest = Receivable::factory()->for($customer)->create([
            'amount_cents' => 1000,
            'due_date' => '2026-11-10',
        ]);

        $response = $this->actingAs($cashier)->post(route('receivables.payments.store', $customer), [
            'method' => PaymentMethod::Pix->value,
            'amount' => '15,00',
        ]);

        $response->assertRedirect();
        $payment = Payment::query()->sole();
        $this->assertNull($payment->tab_id);
        $this->assertSame($customer->id, $payment->customer_id);
        $this->assertSame($cashier->id, $payment->recorded_by);
        $this->assertSame($cashSession->id, $payment->cash_session_id);
        $this->assertSame(PaymentMethod::Pix, $payment->method);
        $this->assertSame(1500, $payment->amount_cents);
        $this->assertSame(1000, $oldest->refresh()->paid_cents);
        $this->assertSame(ReceivableStatus::Paid, $oldest->status);
        $this->assertSame(500, $newest->refresh()->paid_cents);
        $this->assertSame(ReceivableStatus::Open, $newest->status);
        $this->assertSame(1500, PaymentAllocation::query()->sum('amount_cents'));
        $this->assertDatabaseCount('payment_allocations', 2);
    }

    public function test_payment_cannot_exceed_customer_open_balance(): void
    {
        $cashier = User::factory()->cashier()->create();
        CashSession::factory()->for($cashier, 'opener')->create();
        $customer = Customer::factory()->create();
        $receivable = Receivable::factory()->for($customer)->create(['amount_cents' => 1000]);

        $response = $this->actingAs($cashier)->post(route('receivables.payments.store', $customer), [
            'method' => PaymentMethod::Cash->value,
            'amount' => '10,01',
        ]);

        $response->assertSessionHasErrors(['amount' => 'O valor deve ser maior que zero e não pode ultrapassar o que falta receber.']);
        $this->assertSame(0, $receivable->refresh()->paid_cents);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_receivable_payment_requires_open_cash_session(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $receivable = Receivable::factory()->for($customer)->create(['amount_cents' => 1000]);

        $response = $this->actingAs($cashier)->post(route('receivables.payments.store', $customer), [
            'method' => PaymentMethod::Pix->value,
            'amount' => '10,00',
        ]);

        $response->assertSessionHasErrors(['method' => 'Abra o caixa antes de registrar recebimentos.']);
        $this->assertSame(0, $receivable->refresh()->paid_cents);
        $this->assertDatabaseCount('payments', 0);
    }
}
