<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MenuSettingController extends Controller
{
    public function edit(): View
    {
        return view('menu-settings.edit', [
            'branch' => app('currentBranch'),
            'categories' => MenuCategory::query()->withCount('products')->orderBy('position')->orderBy('name')->get(),
            'productsOnMenuCount' => Product::query()->where('show_on_menu', true)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $orderingEnabled = $request->boolean('menu_ordering_enabled');

        Branch::query()->whereKey(app('currentBranch')->id)->update(['menu_ordering_enabled' => $orderingEnabled]);

        return redirect()->route('menu-settings.edit')->with('success', $orderingEnabled
            ? 'Pedidos pelo cardápio ativados nesta filial.'
            : 'Pedidos pelo cardápio desativados nesta filial. O cardápio continua visível.');
    }
}
