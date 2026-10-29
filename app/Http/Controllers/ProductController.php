<?php

namespace App\Http\Controllers;

use App\Actions\CreateProduct;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\BranchProduct;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\ProductSaleType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::query()
                ->with(['branchConfiguration', 'menuCategory'])
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
        return view('products.create', [
            'saleTypes' => ProductSaleType::cases(),
            'menuCategories' => $this->menuCategories(),
        ]);
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        $photoPath = $this->storePhoto($request->file('photo'));

        try {
            $product = $createProduct->handle([
                ...$this->attributes($request->validated(), true),
                'photo_path' => $photoPath,
            ], $request->user());
        } catch (Throwable $exception) {
            $this->deletePhoto($photoPath);

            throw $exception;
        }

        $this->syncGallery($product, $request->file('gallery_photos', []), []);

        return redirect()->route('products.index')->with('success', 'Produto cadastrado com sucesso.');
    }

    public function edit(Product $product): View
    {
        $product->load(['branchConfiguration', 'photos']);

        return view('products.edit', [
            'product' => $product,
            'saleTypes' => ProductSaleType::cases(),
            'menuCategories' => $this->menuCategories(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $this->ensureGalleryFits($product, $request->file('gallery_photos', []), $validated['remove_gallery_photos'] ?? []);
        $attributes = $this->attributes($validated, false);
        $previousPhotoPath = $product->photo_path;
        $newPhotoPath = $this->storePhoto($request->file('photo'));
        $photoPath = $newPhotoPath ?? ($validated['remove_photo'] ? null : $previousPhotoPath);

        try {
            $product->update([...$attributes, 'photo_path' => $photoPath]);
            BranchProduct::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'price_cents' => $attributes['price_cents'],
                    'min_stock_milliunits' => $attributes['min_stock_milliunits'],
                    'active' => $attributes['active'],
                ],
            );
        } catch (Throwable $exception) {
            $this->deletePhoto($newPhotoPath);

            throw $exception;
        }

        if ($photoPath !== $previousPhotoPath) {
            $this->deletePhoto($previousPhotoPath);
        }

        $this->syncGallery($product, $request->file('gallery_photos', []), $validated['remove_gallery_photos'] ?? []);

        return redirect()->route('products.index')->with('success', 'Produto atualizado com sucesso.');
    }

    /**
     * @param  array<int, UploadedFile>  $uploads
     * @param  array<int, int|string>  $removedPhotoIds
     */
    private function ensureGalleryFits(Product $product, array $uploads, array $removedPhotoIds): void
    {
        $remainingPhotos = $product->photos()->whereNotIn('id', $removedPhotoIds)->count();

        if ($remainingPhotos + count($uploads) > StoreProductRequest::MAX_GALLERY_PHOTOS) {
            throw ValidationException::withMessages([
                'gallery_photos' => 'O produto pode ter no máximo '.StoreProductRequest::MAX_GALLERY_PHOTOS.' fotos extras. Remova alguma antes de enviar novas.',
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $uploads
     * @param  array<int, int|string>  $removedPhotoIds
     */
    private function syncGallery(Product $product, array $uploads, array $removedPhotoIds): void
    {
        $removedPhotos = $product->photos()->whereIn('id', $removedPhotoIds)->get();
        $nextPosition = (int) $product->photos()->max('position') + 1;

        foreach ($uploads as $upload) {
            $product->photos()->create(['path' => $this->storePhoto($upload), 'position' => $nextPosition++]);
        }

        $removedPhotos->each(function (ProductPhoto $photo): void {
            $photo->delete();
            $this->deletePhoto($photo->path);
        });
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
            'menu_category_id' => $validated['menu_category_id'] === null ? null : (int) $validated['menu_category_id'],
            'description' => $validated['description'],
            'show_on_menu' => $validated['show_on_menu'],
        ];
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    private function menuCategories(): Collection
    {
        return MenuCategory::query()->orderBy('position')->orderBy('name')->get();
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        $path = $photo->store('product-photos', 'public');

        if ($path === false) {
            throw new RuntimeException('Não foi possível armazenar a foto do produto.');
        }

        return $path;
    }

    private function deletePhoto(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
