<?php

namespace App\Http\Controllers;

use App\Actions\CreateProduct;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\BranchProduct;
use App\Models\Product;
use App\ProductSaleType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::query()
                ->with('branchConfiguration')
                ->orderBy('name')
                ->paginate(20),
            'lowStockCount' => Product::query()
                ->whereHas('branchConfiguration', fn ($query) => $query
                    ->where('active', true)
                    ->whereNotNull('stock_milliunits')
                    ->whereNotNull('min_stock_milliunits')
                    ->whereColumn('stock_milliunits', '<=', 'min_stock_milliunits'))
                ->count(),
        ]);
    }

    public function create(): View
    {
        return view('products.create', ['saleTypes' => ProductSaleType::cases()]);
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        $createProduct->handle($this->attributes($request->validated(), true), $request->user());

        return redirect()->route('products.index')->with('success', 'Produto cadastrado com sucesso.');
    }

    public function edit(Product $product): View
    {
        $product->load('branchConfiguration');

        return view('products.edit', [
            'product' => $product,
            'saleTypes' => ProductSaleType::cases(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $attributes = $this->attributes($request->validated(), false);
        $product->update($attributes);
        BranchProduct::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'price_cents' => $attributes['price_cents'],
                'min_stock_milliunits' => $attributes['min_stock_milliunits'],
                'active' => $attributes['active'],
            ],
        );

        return redirect()->route('products.index')->with('success', 'Produto atualizado com sucesso.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, bool $includeInitialStock): array
    {
        return [
            'name' => $validated['name'],
            'sale_type' => $validated['sale_type'],
            'price_cents' => (int) round((float) $validated['price'] * 100),
            ...($includeInitialStock ? [
                'stock_milliunits' => $validated['stock'] === null
                    ? null
                    : (int) round((float) $validated['stock'] * 1000),
            ] : []),
            'min_stock_milliunits' => $validated['min_stock'] === null
                ? null
                : (int) round((float) $validated['min_stock'] * 1000),
            'active' => $validated['active'],
        ];
    }
}
