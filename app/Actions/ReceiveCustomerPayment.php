<?php

namespace App\Actions;

use App\CashSessionStatus;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receivable;
use App\Models\User;
use App\PaymentMethod;
use App\ReceivableStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveCustomerPayment
{
    public function handle(Customer $customer, User $user, PaymentMethod $method, int $amountCents): Payment
    {
        return DB::transaction(function () use ($customer, $user, $method, $amountCents): Payment {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());

            if (! in_array($method, PaymentMethod::immediateCases(), true)) {
                throw ValidationException::withMessages(['method' => 'Selecione uma forma de recebimento válida.']);
            }

            $receivables = Receivable::query()
                ->visibleForCustomerSharing()
                ->whereBelongsTo($lockedCustomer)
                ->where('status', ReceivableStatus::Open)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $openBalanceCents = $receivables->sum(fn (Receivable $receivable): int => $receivable->amount_cents - $receivable->paid_cents);

            if ($amountCents <= 0 || $amountCents > $openBalanceCents) {
                throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero e não pode ultrapassar o que falta receber.']);
            }

            $cashSession = CashSession::query()
                ->where('status', CashSessionStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($cashSession === null) {
                throw ValidationException::withMessages(['method' => 'Abra o caixa antes de registrar recebimentos.']);
            }

            $paidAt = now();
            $payment = Payment::query()->create([
                'tab_id' => null,
                'customer_id' => $lockedCustomer->getKey(),
                'cash_session_id' => $cashSession->getKey(),
                'recorded_by' => $user->getKey(),
                'method' => $method,
                'amount_cents' => $amountCents,
                'paid_at' => $paidAt,
            ]);
            $remainingCents = $amountCents;

            foreach ($receivables as $receivable) {
                if ($remainingCents === 0) {
                    break;
                }

                $receivableBalance = $receivable->amount_cents - $receivable->paid_cents;
                $allocationCents = min($remainingCents, $receivableBalance);
                $newPaidCents = $receivable->paid_cents + $allocationCents;
                $fullyPaid = $newPaidCents === $receivable->amount_cents;

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->getKey(),
                    'receivable_id' => $receivable->getKey(),
                    'amount_cents' => $allocationCents,
                ]);

                $receivable->update([
                    'paid_cents' => $newPaidCents,
                    'status' => $fullyPaid ? ReceivableStatus::Paid : ReceivableStatus::Open,
                    'paid_at' => $fullyPaid ? $paidAt : null,
                ]);
                $remainingCents -= $allocationCents;
            }

            return $payment;
        });
    }
}
