<?php

namespace App\Actions;

use App\CashMovementType;
use App\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCashMovement
{
    public function handle(CashSession $cashSession, User $user, CashMovementType $type, int $amountCents, string $description): CashMovement
    {
        return DB::transaction(function () use ($cashSession, $user, $type, $amountCents, $description): CashMovement {
            $lockedSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->getKey());

            if ($lockedSession->status !== CashSessionStatus::Open) {
                throw ValidationException::withMessages(['cash_session' => 'Este caixa já foi fechado.']);
            }

            return CashMovement::query()->create([
                'cash_session_id' => $lockedSession->getKey(),
                'recorded_by' => $user->getKey(),
                'type' => $type,
                'amount_cents' => $amountCents,
                'description' => $description,
                'occurred_at' => now(),
            ]);
        });
    }
}
