<?php

namespace App\Actions;

use App\CardStatus;
use App\CashSessionStatus;
use App\Models\Card;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\EstablishmentSetting;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\PaymentMethod;
use App\ReceivableStatus;
use App\TabStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseTab
{
    /** @param array<string, int> $paymentAmounts */
    public function handle(Tab $tab, ?Customer $customer, User $user, PaymentMethod $paymentMethod, array $paymentAmounts = []): void
    {
        DB::transaction(function () use ($tab, $customer, $user, $paymentMethod, $paymentAmounts): void {
            $lockedTab = Tab::query()->lockForUpdate()->findOrFail($tab->getKey());
            $lockedCustomer = $customer === null
                ? null
                : Customer::query()->lockForUpdate()->findOrFail($customer->getKey());
            $card = Card::query()->lockForUpdate()->findOrFail($lockedTab->card_id);

            if ($lockedTab->status !== TabStatus::Open) {
                throw ValidationException::withMessages(['tab' => 'Esta comanda já foi fechada.']);
            }

            if ($lockedTab->total_cents === 0) {
                throw ValidationException::withMessages(['tab' => 'Adicione pelo menos um item antes de fechar a comanda.']);
            }

            if ($lockedCustomer !== null && ! $lockedCustomer->active) {
                throw ValidationException::withMessages(['customer_id' => 'O cliente selecionado não está ativo.']);
            }

            $closedAt = now();
            $cashSession = null;

            if (! $paymentMethod->createsReceivable()) {
                $cashSession = CashSession::query()
                    ->where('status', CashSessionStatus::Open)
                    ->lockForUpdate()
                    ->first();

                if ($cashSession === null) {
                    throw ValidationException::withMessages(['payment_method' => 'Abra o caixa antes de registrar recebimentos.']);
                }
            }

            if ($paymentMethod->createsReceivable()) {
                if ($lockedCustomer === null) {
                    throw ValidationException::withMessages(['customer_id' => 'Selecione o cliente que receberá a anotação.']);
                }

                $this->createReceivables($lockedTab, $lockedCustomer, $closedAt->toImmutable());
            } elseif ($paymentMethod === PaymentMethod::Mixed) {
                if (count($paymentAmounts) < 2 || array_sum($paymentAmounts) !== $lockedTab->total_cents) {
                    throw ValidationException::withMessages(['payments' => 'A soma dos pagamentos deve ser igual ao total da comanda.']);
                }

                foreach ($paymentAmounts as $method => $amountCents) {
                    $this->createPayment($lockedTab, $cashSession, $user, PaymentMethod::from($method), $amountCents, $closedAt);
                }
            } else {
                $this->createPayment($lockedTab, $cashSession, $user, $paymentMethod, $lockedTab->total_cents, $closedAt);
            }

            $lockedTab->update([
                'customer_id' => $lockedCustomer?->getKey(),
                'closed_by' => $user->getKey(),
                'status' => TabStatus::Closed,
                'closed_at' => $closedAt,
            ]);

            $card->update(['status' => CardStatus::Available]);
        });
    }

    private function createPayment(Tab $tab, CashSession $cashSession, User $user, PaymentMethod $method, int $amountCents, Carbon $paidAt): void
    {
        Payment::query()->create([
            'tab_id' => $tab->getKey(),
            'cash_session_id' => $cashSession->getKey(),
            'recorded_by' => $user->getKey(),
            'method' => $method,
            'amount_cents' => $amountCents,
            'paid_at' => $paidAt,
        ]);
    }

    private function createReceivables(Tab $tab, Customer $customer, CarbonImmutable $closedAt): void
    {
        $amountsByCustomer = [];
        $items = TabItem::query()
            ->whereBelongsTo($tab)
            ->with('shares')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($item->shares->isEmpty()) {
                $amountsByCustomer[$customer->getKey()] = ($amountsByCustomer[$customer->getKey()] ?? 0) + $item->total_cents;

                continue;
            }

            foreach ($item->shares as $share) {
                $amountsByCustomer[$share->customer_id] = ($amountsByCustomer[$share->customer_id] ?? 0) + $share->amount_cents;
            }
        }

        $customers = Customer::query()
            ->whereKey(array_keys($amountsByCustomer))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $generalDueDay = EstablishmentSetting::query()->value('general_due_day') ?? 10;

        foreach ($amountsByCustomer as $customerId => $amountCents) {
            $receivableCustomer = $customers->get($customerId);

            Receivable::query()->create([
                'customer_id' => $customerId,
                'tab_id' => $tab->getKey(),
                'amount_cents' => $amountCents,
                'due_date' => $this->dueDate($closedAt, $receivableCustomer?->due_day ?? $generalDueDay)->toDateString(),
                'status' => ReceivableStatus::Open,
            ]);
        }
    }

    private function dueDate(CarbonImmutable $closedAt, int $dueDay): CarbonImmutable
    {
        $month = $closedAt->startOfMonth();
        $candidate = $month->day(min($dueDay, $month->daysInMonth));

        if ($candidate->startOfDay()->isBefore($closedAt->startOfDay())) {
            $month = $month->addMonth();

            return $month->day(min($dueDay, $month->daysInMonth));
        }

        return $candidate;
    }
}
