<?php

namespace App\Http\Controllers;

use App\Actions\SplitTabItem;
use App\Http\Requests\StoreTabItemShareRequest;
use App\Models\Tab;
use App\Models\TabItem;
use Illuminate\Http\RedirectResponse;

class TabItemShareController extends Controller
{
    public function store(StoreTabItemShareRequest $request, Tab $tab, TabItem $item, SplitTabItem $splitTabItem): RedirectResponse
    {
        abort_unless($item->tab_id === $tab->getKey(), 404);
        $splitTabItem->handle($tab, $item, $request->collect('customer_ids')->map(fn ($id): int => (int) $id)->all());

        return redirect()->route('tabs.show', $tab)->with('success', 'Item dividido entre os clientes selecionados.');
    }

    public function destroy(Tab $tab, TabItem $item, SplitTabItem $splitTabItem): RedirectResponse
    {
        abort_unless($item->tab_id === $tab->getKey(), 404);
        $splitTabItem->clear($tab, $item);

        return redirect()->route('tabs.show', $tab)->with('success', 'Rateio removido do item.');
    }
}
