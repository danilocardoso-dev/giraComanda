<?php

namespace App\Actions;

use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockMovement
{
    public function handle(Product $product, User $user, StockMovementType $type, int $quantityMilliunits, string $description): StockMovement
    {
        return DB::transaction(function () use ($product, $user, $type, $quantityMilliunits, $description): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $configuration = BranchProduct::query()
                ->where('product_id', $lockedProduct->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $currentBalance = $configuration->stock_milliunits;

            if (! in_array($type, StockMovementType::manualCases(), true)) {
                throw ValidationException::withMessages(['type' => 'Selecione um tipo de movimento manual válido.']);
            }

            if ($type !== StockMovementType::Adjustment && $currentBalance === null) {
                throw ValidationException::withMessages(['quantity' => 'Faça um ajuste de inventário para ativar o controle deste produto.']);
            }

            if ($type === StockMovementType::Entry) {
                $newBalance = $currentBalance + $quantityMilliunits;
                $quantityDelta = $quantityMilliunits;
            } elseif ($type === StockMovementType::ManualOut) {
                if ($currentBalance < $quantityMilliunits) {
                    throw ValidationException::withMessages(['quantity' => 'A saída não pode ultrapassar o saldo disponível.']);
                }

                $newBalance = $currentBalance - $quantityMilliunits;
                $quantityDelta = -$quantityMilliunits;
            } else {
                $newBalance = $quantityMilliunits;
                $quantityDelta = $newBalance - ($currentBalance ?? 0);
            }

            $configuration->update(['stock_milliunits' => $newBalance]);
            $lockedProduct->update(['stock_milliunits' => $newBalance]);

            return StockMovement::query()->create([
                'product_id' => $lockedProduct->getKey(),
                'recorded_by' => $user->getKey(),
                'type' => $type,
                'quantity_delta_milliunits' => $quantityDelta,
                'balance_after_milliunits' => $newBalance,
                'description' => $description,
                'occurred_at' => now(),
            ]);
        });
    }
}
