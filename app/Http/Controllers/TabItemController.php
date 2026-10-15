<?php

namespace App\Http\Controllers;

use App\Actions\AddTabItem;
use App\Actions\RemoveTabItem;
use App\Http\Requests\StoreTabItemRequest;
use App\Models\Product;
use App\Models\Tab;
use App\Models\TabItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TabItemController extends Controller
{
    public function store(StoreTabItemRequest $request, Tab $tab, AddTabItem $addTabItem): RedirectResponse
    {
        $product = Product::query()->with('branchConfiguration')->findOrFail($request->integer('product_id'));
        $addTabItem->handle($tab, $product, $request->user(), (string) $request->input('quantity'));

        return redirect()->route('tabs.show', $tab)->with('success', 'Item adicionado à comanda.');
    }

    public function destroy(Request $request, Tab $tab, TabItem $item, RemoveTabItem $removeTabItem): RedirectResponse
    {
        abort_unless($item->tab_id === $tab->getKey(), 404);
        $removeTabItem->handle($tab, $item, $request->user());

        return redirect()->route('tabs.show', $tab)->with('success', 'Item removido da comanda.');
    }
}
