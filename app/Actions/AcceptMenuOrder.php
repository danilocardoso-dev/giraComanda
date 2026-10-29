<?php

namespace App\Actions;

use App\CardStatus;
use App\MenuOrderStatus;
use App\Models\Card;
use App\Models\MenuOrder;
use App\Models\Tab;
use App\Models\User;
use App\TabStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptMenuOrder
{
    public function __construct(private OpenTab $openTab, private AddTabItem $addTabItem) {}

    /**
     * Move a pending menu order into the card's tab, opening one when needed.
     */
    public function handle(MenuOrder $order, User $user): Tab
    {
        return DB::transaction(function () use ($order, $user): Tab {
            $lockedOrder = MenuOrder::query()->with('items.product')->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->status !== MenuOrderStatus::Pending) {
                throw ValidationException::withMessages(['menu_order' => 'Este pedido já foi analisado.']);
            }

            $tab = $this->resolveTab($lockedOrder, $user);

            foreach ($lockedOrder->items as $item) {
                $this->addTabItem->handle($tab, $item->product, $user, (string) $item->quantity);
            }

            $lockedOrder->update([
                'tab_id' => $tab->getKey(),
                'status' => MenuOrderStatus::Accepted,
                'reviewed_by' => $user->getKey(),
                'reviewed_at' => now(),
            ]);

            return $tab;
        });
    }

    private function resolveTab(MenuOrder $order, User $user): Tab
    {
        if ($order->tab_id !== null) {
            $tab = Tab::query()->findOrFail($order->tab_id);

            if ($tab->status !== TabStatus::Open) {
                throw ValidationException::withMessages(['menu_order' => 'A comanda deste pedido já foi fechada. Recuse o pedido e oriente o cliente.']);
            }

            return $tab;
        }

        $card = Card::query()->findOrFail($order->card_id);

        if ($card->status === CardStatus::Available) {
            return $this->openTab->handle($card, $user);
        }

        $openTab = Tab::query()
            ->whereBelongsTo($card)
            ->where('status', TabStatus::Open)
            ->latest('id')
            ->first();

        if ($card->status === CardStatus::Blocked || $openTab === null) {
            throw ValidationException::withMessages(['menu_order' => "O cartão {$card->code} não está disponível para receber este pedido."]);
        }

        return $openTab;
    }
}
