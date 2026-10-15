<?php

namespace App\Actions;

use App\CashMovementType;
use App\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCashSession
{
    public function handle(CashSession $cashSession, User $user, int $countedAmountCents, ?string $notes): CashSession
    {
        return DB::transaction(function () use ($cashSession, $user, $countedAmountCents, $notes): CashSession {
            $lockedSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->getKey());

            if ($lockedSession->status !== CashSessionStatus::Open) {
                throw ValidationException::withMessages(['cash_session' => 'Este caixa já foi fechado.']);
            }

            $cashPaymentsCents = Payment::query()
                ->whereBelongsTo($lockedSession)
                ->where('method', PaymentMethod::Cash)
                ->sum('amount_cents');
            $suppliesCents = CashMovement::query()
                ->whereBelongsTo($lockedSession)
                ->where('type', CashMovementType::Supply)
                ->sum('amount_cents');
            $withdrawalsCents = CashMovement::query()
                ->whereBelongsTo($lockedSession)
                ->where('type', CashMovementType::Withdrawal)
                ->sum('amount_cents');
            $expectedAmountCents = $lockedSession->opening_amount_cents + $cashPaymentsCents + $suppliesCents - $withdrawalsCents;

            $lockedSession->update([
                'closed_by' => $user->getKey(),
                'status' => CashSessionStatus::Closed,
                'expected_amount_cents' => $expectedAmountCents,
                'counted_amount_cents' => $countedAmountCents,
                'difference_cents' => $countedAmountCents - $expectedAmountCents,
                'closed_at' => now(),
                'notes' => filled($notes) ? $notes : null,
            ]);

            return $lockedSession;
        });
    }
}
