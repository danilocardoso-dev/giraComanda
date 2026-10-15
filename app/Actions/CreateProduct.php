<?php

namespace App\Actions;

use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    /**
     * @param  array{name: string, sale_type: string, price_cents: int, stock_milliunits: ?int, min_stock_milliunits: ?int, active: bool}  $attributes
     */
    public function handle(array $attributes, User $user): Product
    {
        return DB::transaction(function () use ($attributes, $user): Product {
            $product = Product::query()->create($attributes);
            BranchProduct::query()->create([
                'product_id' => $product->getKey(),
                'price_cents' => $attributes['price_cents'],
                'stock_milliunits' => $attributes['stock_milliunits'],
                'min_stock_milliunits' => $attributes['min_stock_milliunits'],
                'active' => $attributes['active'],
            ]);

            if ($attributes['stock_milliunits'] !== null) {
                StockMovement::query()->create([
                    'product_id' => $product->getKey(),
                    'recorded_by' => $user->getKey(),
                    'type' => StockMovementType::Initial,
                    'quantity_delta_milliunits' => $attributes['stock_milliunits'],
                    'balance_after_milliunits' => $attributes['stock_milliunits'],
                    'description' => 'Saldo informado no cadastro do produto.',
                    'occurred_at' => now(),
                ]);
            }

            return $product;
        });
    }
}
