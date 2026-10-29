<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuCategoryRequest;
use App\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;

class MenuCategoryController extends Controller
{
    public function store(StoreMenuCategoryRequest $request): RedirectResponse
    {
        MenuCategory::query()->create($request->validated());

        return redirect()->route('menu-settings.edit')->with('success', 'Categoria criada com sucesso.');
    }

    public function update(StoreMenuCategoryRequest $request, MenuCategory $menuCategory): RedirectResponse
    {
        $menuCategory->update($request->validated());

        return redirect()->route('menu-settings.edit')->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(MenuCategory $menuCategory): RedirectResponse
    {
        $menuCategory->delete();

        return redirect()->route('menu-settings.edit')->with('success', 'Categoria removida. Os produtos dela aparecem em "Outros".');
    }
}
