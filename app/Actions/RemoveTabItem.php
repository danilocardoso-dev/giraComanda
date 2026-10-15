<?php

namespace App\Actions;

use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\StockMovementType;
use App\TabStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveTabItem
{
    public function handle(Tab $tab, TabItem $item, User $user): void
    {
        DB::transaction(function () use ($tab, $item, $user): void {
            $lockedTab = Tab::query()->lockForUpdate()->findOrFail($tab->getKey());
            $lockedItem = TabItem::query()->lockForUpdate()->findOrFail($item->getKey());

            if ($lockedTab->status !== TabStatus::Open) {
                throw ValidationException::withMessages(['tab' => 'Esta comanda já foi fechada.']);
            }

            if ($lockedItem->product_id !== null) {
                $product = Product::query()->lockForUpdate()->find($lockedItem->product_id);
                $configuration = $product === null
                    ? null
                    : BranchProduct::query()
                        ->where('product_id', $product->getKey())
                        ->lockForUpdate()
                        ->first();

                if ($configuration?->stock_milliunits !== null) {
                    $newBalance = $configuration->stock_milliunits + $lockedItem->quantity_milliunits;
                    $configuration->update(['stock_milliunits' => $newBalance]);
                    $product->update(['stock_milliunits' => $newBalance]);
                    StockMovement::query()->create([
                        'product_id' => $product->getKey(),
                        'recorded_by' => $user->getKey(),
                        'tab_id' => $lockedTab->getKey(),
                        'type' => StockMovementType::SaleReversal,
                        'quantity_delta_milliunits' => $lockedItem->quantity_milliunits,
                        'balance_after_milliunits' => $newBalance,
                        'description' => "Item removido da comanda {$lockedTab->card_id}.",
                        'occurred_at' => now(),
                    ]);
                }
            }

            $lockedTab->update([
                'total_cents' => max(0, $lockedTab->total_cents - $lockedItem->total_cents),
            ]);

            $lockedItem->delete();
        });
    }
}
