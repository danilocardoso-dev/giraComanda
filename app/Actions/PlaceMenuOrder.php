<?php

namespace App\Actions;

use App\CardStatus;
use App\MenuOrderStatus;
use App\Models\Card;
use App\Models\MenuOrder;
use App\Models\Product;
use App\Models\Tab;
use App\ProductSaleType;
use App\TabStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceMenuOrder
{
    /**
     * Pending orders a single card may keep waiting for the attendant at once.
     */
    public const MAX_PENDING_ORDERS_PER_CARD = 3;

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function handle(Card $card, array $items, ?string $customerName, ?string $notes): MenuOrder
    {
        if (! app('currentBranch')->menu_ordering_enabled) {
            throw ValidationException::withMessages(['items' => 'Os pedidos pelo cardápio estão desativados neste momento.']);
        }

        return DB::transaction(function () use ($card, $items, $customerName, $notes): MenuOrder {
            $lockedCard = Card::query()->lockForUpdate()->findOrFail($card->getKey());

            if ($lockedCard->status === CardStatus::Blocked) {
                throw ValidationException::withMessages(['items' => 'Este cartão não pode receber pedidos.']);
            }

            $pendingOrders = MenuOrder::query()
                ->whereBelongsTo($lockedCard)
                ->where('status', MenuOrderStatus::Pending)
                ->count();

            if ($pendingOrders >= self::MAX_PENDING_ORDERS_PER_CARD) {
                throw ValidationException::withMessages(['items' => 'Aguarde o atendente confirmar os pedidos anteriores antes de enviar outro.']);
            }

            $quantities = collect($items)
                ->groupBy('product_id')
                ->map(fn ($lines): int => (int) $lines->sum('quantity'));
            $products = Product::query()
                ->with('branchConfiguration')
                ->whereIn('id', $quantities->keys())
                ->where('show_on_menu', true)
                ->where('sale_type', ProductSaleType::Unit)
                ->get()
                ->keyBy('id');

            if ($products->count() !== $quantities->count() || $products->contains(fn (Product $product): bool => ! $product->active)) {
                throw ValidationException::withMessages(['items' => 'Um dos itens escolhidos não está mais disponível. Atualize o cardápio e tente novamente.']);
            }

            foreach ($quantities as $productId => $quantity) {
                $stock = $products[$productId]->stock_milliunits;

                if ($stock !== null && $stock < $quantity * 1000) {
                    throw ValidationException::withMessages(['items' => "Estoque insuficiente para {$products[$productId]->name}."]);
                }
            }

            $openTab = Tab::query()
                ->whereBelongsTo($lockedCard)
                ->where('status', TabStatus::Open)
                ->latest('id')
                ->first();

            $order = MenuOrder::query()->create([
                'card_id' => $lockedCard->getKey(),
                'tab_id' => $openTab?->getKey(),
                'status' => MenuOrderStatus::Pending,
                'customer_name' => $customerName,
                'notes' => $notes,
                'total_cents' => 0,
            ]);

            $totalCents = 0;

            foreach ($quantities as $productId => $quantity) {
                $product = $products[$productId];
                $lineTotalCents = $product->price_cents * $quantity;
                $totalCents += $lineTotalCents;

                $order->items()->create([
                    'product_id' => $product->getKey(),
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price_cents' => $product->price_cents,
                    'total_cents' => $lineTotalCents,
                ]);
            }

            $order->update(['total_cents' => $totalCents]);

            return $order;
        });
    }
}
