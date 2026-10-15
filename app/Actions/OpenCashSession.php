<?php

namespace App\Actions;

use App\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OpenCashSession
{
    public function handle(User $user, int $openingAmountCents): CashSession
    {
        return Cache::lock('cash-session-open', 10)->block(5, function () use ($user, $openingAmountCents): CashSession {
            if (CashSession::query()->where('status', CashSessionStatus::Open)->exists()) {
                throw ValidationException::withMessages(['opening_amount' => 'Já existe um caixa aberto. Feche-o antes de abrir outro.']);
            }

            return CashSession::query()->create([
                'opened_by' => $user->getKey(),
                'status' => CashSessionStatus::Open,
                'opening_amount_cents' => $openingAmountCents,
                'opened_at' => now(),
            ]);
        });
    }
}
