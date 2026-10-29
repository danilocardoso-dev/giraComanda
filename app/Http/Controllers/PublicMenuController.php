<?php

namespace App\Http\Controllers;

use App\Actions\PlaceMenuOrder;
use App\Http\Requests\StoreMenuOrderRequest;
use App\Models\Card;
use App\Models\EstablishmentSetting;
use App\Models\MenuCategory;
use App\Models\MenuOrder;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\Tab;
use App\TabStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMenuController extends Controller
{
    public function show(Request $request): View
    {
        $card = $this->card($request);
        $openTab = $this->openTab($card);

        return view('menu.show', [
            'card' => $card,
            'branch' => app('currentBranch'),
            'settings' => EstablishmentSetting::query()->first(),
            'categories' => $this->menuSections(),
            'openTab' => $openTab?->load('items'),
            'orders' => MenuOrder::query()
                ->forCurrentVisit($card, $openTab)
                ->with('items')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(StoreMenuOrderRequest $request, PlaceMenuOrder $placeMenuOrder): RedirectResponse
    {
        $card = $this->card($request);
        $validated = $request->validated();

        $placeMenuOrder->handle($card, $validated['items'], $validated['customer_name'], $validated['notes']);

        return redirect()
            ->route('menu.show', $card->qr_token)
            ->with('success', 'Pedido enviado! Aguarde a confirmação do atendente.');
    }

    public function status(Request $request): JsonResponse
    {
        $card = $this->card($request);
        $orders = MenuOrder::query()
            ->forCurrentVisit($card, $this->openTab($card))
            ->latest('id')
            ->limit(10)
            ->get(['id', 'status']);

        return response()->json([
            'signature' => $orders->map(fn (MenuOrder $order): string => "{$order->id}:{$order->status->value}")->implode('|'),
        ]);
    }

    public function photo(string $qrToken, Product $product, ?ProductPhoto $photo = null): StreamedResponse
    {
        $path = $photo?->path ?? $product->photo_path;

        abort_if(! $product->show_on_menu || $path === null || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logo(): StreamedResponse
    {
        $logoPath = EstablishmentSetting::query()->value('logo_path');

        abort_if($logoPath === null || ! Storage::disk('public')->exists($logoPath), 404);

        return Storage::disk('public')->response($logoPath, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function card(Request $request): Card
    {
        return $request->attributes->get('menuCard');
    }

    private function openTab(Card $card): ?Tab
    {
        return Tab::query()
            ->whereBelongsTo($card)
            ->where('status', TabStatus::Open)
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, array{name: string, products: Collection<int, Product>}>
     */
    private function menuSections(): Collection
    {
        $products = Product::query()
            ->with(['branchConfiguration', 'photos'])
            ->where('show_on_menu', true)
            ->whereHas('branchConfiguration', fn ($query) => $query->where('active', true))
            ->orderBy('name')
            ->get();
        $productsByCategory = $products->groupBy(fn (Product $product): int => $product->menu_category_id ?? 0);

        return MenuCategory::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuCategory $category): array => [
                'name' => $category->name,
                'products' => $productsByCategory->get($category->id, collect()),
            ])
            ->push(['name' => 'Outros', 'products' => $productsByCategory->get(0, collect())])
            ->filter(fn (array $section): bool => $section['products']->isNotEmpty())
            ->values();
    }
}
