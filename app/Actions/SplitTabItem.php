<?php

namespace App\Actions;

use App\Models\Tab;
use App\Models\TabItem;
use App\Models\TabItemShare;
use App\TabStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SplitTabItem
{
    /** @param array<int, int> $customerIds */
    public function handle(Tab $tab, TabItem $item, array $customerIds): void
    {
        DB::transaction(function () use ($tab, $item, $customerIds): void {
            $lockedTab = Tab::query()->lockForUpdate()->findOrFail($tab->getKey());
            $lockedItem = TabItem::query()->lockForUpdate()->findOrFail($item->getKey());

            if ($lockedTab->status !== TabStatus::Open) {
                throw ValidationException::withMessages(['tab' => 'Esta comanda já foi fechada.']);
            }

            sort($customerIds);
            $shareCount = count($customerIds);
            $baseAmount = intdiv($lockedItem->total_cents, $shareCount);
            $remainder = $lockedItem->total_cents % $shareCount;

            $lockedItem->shares()->delete();

            foreach ($customerIds as $position => $customerId) {
                TabItemShare::query()->create([
                    'tab_item_id' => $lockedItem->getKey(),
                    'customer_id' => $customerId,
                    'amount_cents' => $baseAmount + ($position < $remainder ? 1 : 0),
                ]);
            }
        });
    }

    public function clear(Tab $tab, TabItem $item): void
    {
        DB::transaction(function () use ($tab, $item): void {
            $lockedTab = Tab::query()->lockForUpdate()->findOrFail($tab->getKey());
            $lockedItem = TabItem::query()->lockForUpdate()->findOrFail($item->getKey());

            if ($lockedTab->status !== TabStatus::Open) {
                throw ValidationException::withMessages(['tab' => 'Esta comanda já foi fechada.']);
            }

            $lockedItem->shares()->delete();
        });
    }
}
