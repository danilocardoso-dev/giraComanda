<?php

namespace App\Actions;

use App\CashSessionStatus;
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

class CancelTab
{
    public function handle(Tab $tab, User $user, string $reason): void
    {
        DB::transaction(function () use ($tab, $user, $reason): void {
            $lockedTab = Tab::query()
                ->with(['card', 'payments.cashSession', 'receivables.allocations'])
                ->lockForUpdate()
                ->findOrFail($tab->getKey());

            if ($lockedTab->status !== TabStatus::Closed) {
                throw ValidationException::withMessages(['tab' => 'Somente uma comanda fechada pode ser cancelada.']);
            }

            if ($lockedTab->payments->contains(fn ($payment): bool => $payment->cashSession?->status === CashSessionStatus::Closed)) {
                throw ValidationException::withMessages(['tab' => 'O caixa desta venda já foi fechado. Registre a correção no próximo caixa com orientação do administrador.']);
            }

            if ($lockedTab->receivables->contains(fn ($receivable): bool => $receivable->paid_cents > 0 || $receivable->allocations->isNotEmpty())) {
                throw ValidationException::withMessages(['tab' => 'Esta anotação já recebeu pagamento e não pode ser cancelada automaticamente.']);
            }

            $items = TabItem::query()->whereBelongsTo($lockedTab)->lockForUpdate()->get();

            foreach ($items as $item) {
                $this->restoreStock($lockedTab, $item, $user);
            }

            foreach ($lockedTab->receivables as $receivable) {
                $receivable->delete();
            }

            foreach ($lockedTab->payments as $payment) {
                $payment->delete();
            }

            $lockedTab->update([
                'status' => TabStatus::Cancelled,
                'cancelled_by' => $user->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
        });
    }

    private function restoreStock(Tab $tab, TabItem $item, User $user): void
    {
        if ($item->product_id === null) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($item->product_id);
        $configuration = $product === null
            ? null
            : BranchProduct::query()
                ->where('product_id', $product->getKey())
                ->lockForUpdate()
                ->first();

        if ($configuration?->stock_milliunits === null) {
            return;
        }

        $newBalance = $configuration->stock_milliunits + $item->quantity_milliunits;
        $configuration->update(['stock_milliunits' => $newBalance]);
        $product->update(['stock_milliunits' => $newBalance]);

        StockMovement::query()->create([
            'product_id' => $product->getKey(),
            'recorded_by' => $user->getKey(),
            'tab_id' => $tab->getKey(),
            'type' => StockMovementType::SaleReversal,
            'quantity_delta_milliunits' => $item->quantity_milliunits,
            'balance_after_milliunits' => $newBalance,
            'description' => "Cancelamento da comanda {$tab->card->code}.",
            'occurred_at' => now(),
        ]);
    }
}
