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

class AddTabItem
{
    public function handle(Tab $tab, Product $product, User $user, string $quantity): TabItem
    {
        return DB::transaction(function () use ($tab, $product, $user, $quantity): TabItem {
            $lockedTab = Tab::query()->lockForUpdate()->findOrFail($tab->getKey());
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $configuration = BranchProduct::query()
                ->where('product_id', $lockedProduct->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTab->status !== TabStatus::Open) {
                throw ValidationException::withMessages(['tab' => 'Esta comanda já foi fechada.']);
            }

            if (! $configuration->active) {
                throw ValidationException::withMessages(['product_id' => 'O produto selecionado não está disponível.']);
            }

            $quantityMilliunits = (int) round((float) $quantity * 1000);

            if ($configuration->stock_milliunits !== null && $configuration->stock_milliunits < $quantityMilliunits) {
                throw ValidationException::withMessages(['quantity' => 'Estoque insuficiente para este lançamento.']);
            }

            $totalCents = (int) round($configuration->price_cents * $quantityMilliunits / 1000);

            $item = TabItem::query()->create([
                'tab_id' => $lockedTab->getKey(),
                'product_id' => $lockedProduct->getKey(),
                'added_by' => $user->getKey(),
                'product_name' => $lockedProduct->name,
                'sale_type' => $lockedProduct->sale_type,
                'quantity_milliunits' => $quantityMilliunits,
                'unit_price_cents' => $configuration->price_cents,
                'total_cents' => $totalCents,
            ]);

            $lockedTab->increment('total_cents', $totalCents);

            if ($configuration->stock_milliunits !== null) {
                $newBalance = $configuration->stock_milliunits - $quantityMilliunits;
                $configuration->update(['stock_milliunits' => $newBalance]);
                $lockedProduct->update(['stock_milliunits' => $newBalance]);
                StockMovement::query()->create([
                    'product_id' => $lockedProduct->getKey(),
                    'recorded_by' => $user->getKey(),
                    'tab_id' => $lockedTab->getKey(),
                    'type' => StockMovementType::Sale,
                    'quantity_delta_milliunits' => -$quantityMilliunits,
                    'balance_after_milliunits' => $newBalance,
                    'description' => "Lançamento na comanda {$lockedTab->card_id}.",
                    'occurred_at' => now(),
                ]);
            }

            return $item;
        });
    }
}
