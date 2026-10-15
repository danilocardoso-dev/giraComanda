<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\StockMovementType;
use Illuminate\Contracts\View\View;

class ProductStockController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Product $product): View
    {
        $product->load('branchConfiguration');

        return view('products.stock', [
            'product' => $product,
            'movementTypes' => StockMovementType::manualCases(),
            'movements' => $product->stockMovements()
                ->with(['recorder', 'tab.card'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate(30),
        ]);
    }
}
