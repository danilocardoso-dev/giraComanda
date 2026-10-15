<?php

namespace App\Actions;

use App\CardStatus;
use App\Models\Card;
use App\Models\Tab;
use App\Models\User;
use App\TabStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenTab
{
    public function handle(Card $card, User $user): Tab
    {
        return DB::transaction(function () use ($card, $user): Tab {
            $lockedCard = Card::query()->lockForUpdate()->findOrFail($card->getKey());

            if ($lockedCard->status !== CardStatus::Available) {
                throw ValidationException::withMessages([
                    'card_id' => 'O cartão selecionado não está disponível.',
                ]);
            }

            $tab = Tab::query()->create([
                'card_id' => $lockedCard->getKey(),
                'opened_by' => $user->getKey(),
                'status' => TabStatus::Open,
                'total_cents' => 0,
                'opened_at' => now(),
            ]);

            $lockedCard->update(['status' => CardStatus::InUse]);

            return $tab;
        });
    }
}
