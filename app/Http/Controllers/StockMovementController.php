<?php

namespace App\Http\Controllers;

use App\Actions\RecordStockMovement;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Product;
use App\StockMovementType;
use Illuminate\Http\RedirectResponse;

class StockMovementController extends Controller
{
    public function store(StoreStockMovementRequest $request, Product $product, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $type = StockMovementType::from($request->string('type')->toString());
        $quantityMilliunits = (int) round($request->float('quantity') * 1000);

        $recordStockMovement->handle(
            $product,
            $request->user(),
            $type,
            $quantityMilliunits,
            $request->string('description')->toString(),
        );

        return redirect()->route('products.stock.show', $product)->with('success', "{$type->label()} registrado com sucesso.");
    }
}
