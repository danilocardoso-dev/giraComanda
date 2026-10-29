@php
    $editing = isset($product) && $product->exists;
    $price = $editing ? number_format($product->price_cents / 100, 2, ',', '.') : '';
    $stock = $editing && $product->stock_milliunits !== null ? number_format($product->stock_milliunits / 1000, 3, '.', '') : '';
    $minStock = $editing && $product->min_stock_milliunits !== null ? number_format($product->min_stock_milliunits / 1000, 3, '.', '') : '';
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="name" class="text-sm font-semibold text-slate-700">Nome do produto</label>
        <input id="name" name="name" value="{{ old('name', $product->name ?? '') }}" required maxlength="255" placeholder="Ex.: Coca-Cola lata"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <label for="sale_type" class="text-sm font-semibold text-slate-700">Forma de venda</label>
        <select id="sale_type" name="sale_type" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            @foreach ($saleTypes as $saleType)
                <option value="{{ $saleType->value }}" @selected(old('sale_type', isset($product) ? $product->sale_type->value : '') === $saleType->value)>{{ $saleType->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('sale_type')" />
    </div>

    <div>
        <label for="price" class="text-sm font-semibold text-slate-700">Preço por unidade ou quilo</label>
        <div class="mt-2 flex rounded-xl border border-slate-300 bg-white focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-500/20">
            <span class="border-r border-slate-200 px-4 py-3 text-slate-500">R$</span>
            <input id="price" name="price" type="text" inputmode="numeric" maxlength="18" data-mask="currency" value="{{ old('price', $price) }}" placeholder="0,00" required class="min-w-0 flex-1 rounded-r-xl px-4 py-3 outline-none">
        </div>
        <x-input-error :messages="$errors->get('price')" />
    </div>

    @if ($editing)
        <div>
            <p class="text-sm font-semibold text-slate-700">Saldo atual de estoque</p>
            <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 font-bold text-slate-800">
                {{ $product->stock_milliunits === null ? 'Não controlado' : number_format($product->stock_milliunits / 1000, 3, ',', '.').' '.$product->sale_type->quantityLabel() }}
            </div>
            <a href="{{ route('products.stock.show', $product) }}" class="mt-2 inline-flex text-xs font-bold text-brand-700 hover:text-brand-900">Gerenciar movimentações →</a>
        </div>
    @else
        <div>
            <label for="stock" class="text-sm font-semibold text-slate-700">Saldo inicial de estoque</label>
            <input id="stock" name="stock" type="number" min="0" step="0.001" value="{{ old('stock', $stock) }}" placeholder="Deixe vazio para não controlar"
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            <p class="mt-2 text-xs text-slate-500">Use unidades inteiras ou quilos com até três casas decimais.</p>
            <x-input-error :messages="$errors->get('stock')" />
        </div>
    @endif

    <div>
        <label for="min_stock" class="text-sm font-semibold text-slate-700">Alerta de estoque baixo</label>
        <input id="min_stock" name="min_stock" type="number" min="0" step="0.001" value="{{ old('min_stock', $minStock) }}" placeholder="Opcional"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <p class="mt-2 text-xs text-slate-500">O produto será destacado quando atingir este saldo.</p>
        <x-input-error :messages="$errors->get('min_stock')" />
    </div>

    <div class="flex items-end">
        <input type="hidden" name="active" value="0">
        <label class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <input type="checkbox" name="active" value="1" @checked((bool) old('active', $product->active ?? true)) class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
            <span class="text-sm font-semibold text-slate-800">Produto ativo para lançamentos</span>
        </label>
    </div>
</div>

<div class="mt-8 border-t border-slate-200 pt-6">
    <h3 class="text-lg font-bold text-slate-950">Cardápio digital</h3>
    <p class="mt-1 text-sm text-slate-600">Como o produto aparece para o cliente que escaneia o QR Code do cartão.</p>

    <div class="mt-5 grid gap-6 md:grid-cols-2">
        <div class="md:col-span-2">
            <input type="hidden" name="show_on_menu" value="0">
            <label class="flex items-center gap-3 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3">
                <input type="checkbox" name="show_on_menu" value="1" @checked((bool) old('show_on_menu', $product->show_on_menu ?? false)) class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                <span class="text-sm font-semibold text-slate-800">Mostrar no cardápio digital</span>
            </label>
            <p class="mt-2 text-xs text-slate-500">Produtos vendidos por quilo aparecem no cardápio, mas são lançados pelo atendente.</p>
        </div>

        <div>
            <label for="menu_category_id" class="text-sm font-semibold text-slate-700">Categoria</label>
            <select id="menu_category_id" name="menu_category_id" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                <option value="">Outros</option>
                @foreach ($menuCategories as $menuCategory)
                    <option value="{{ $menuCategory->id }}" @selected((string) old('menu_category_id', $product->menu_category_id ?? '') === (string) $menuCategory->id)>{{ $menuCategory->name }}</option>
                @endforeach
            </select>
            <a href="{{ route('menu-settings.edit') }}" class="mt-2 inline-flex text-xs font-bold text-brand-700 hover:text-brand-900">Gerenciar categorias →</a>
            <x-input-error :messages="$errors->get('menu_category_id')" />
        </div>

        <div>
            <label for="photo" class="text-sm font-semibold text-slate-700">Foto</label>
            <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp"
                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-100 file:px-3 file:py-1.5 file:font-semibold file:text-brand-800">
            <p class="mt-2 text-xs text-slate-500">PNG, JPG ou WebP com até 3 MB.</p>
            <x-input-error :messages="$errors->get('photo')" />
            @if ($editing && $product->photo_path)
                <div class="mt-3 flex items-center gap-3">
                    <img src="{{ route('products.photo', ['product' => $product, 'v' => $product->updated_at?->timestamp]) }}" alt="Foto atual de {{ $product->name }}" class="size-16 rounded-xl border border-slate-200 object-cover">
                    <input type="hidden" name="remove_photo" value="0">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remove_photo" value="1" class="size-4 rounded border-slate-300 text-red-600 focus:ring-red-500">
                        Remover foto atual
                    </label>
                </div>
            @endif
        </div>

        <div class="md:col-span-2">
            <label for="gallery_photos" class="text-sm font-semibold text-slate-700">Mais fotos <span class="font-normal text-slate-400">(até {{ \App\Http\Requests\StoreProductRequest::MAX_GALLERY_PHOTOS }})</span></label>
            <p class="mt-1 text-xs text-slate-500">Outros ângulos, o prato montado, o acompanhamento. O cliente vê ao tocar no produto do cardápio.</p>
            @if ($editing && $product->photos->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach ($product->photos as $galleryPhoto)
                        <label class="group relative block cursor-pointer">
                            <img src="{{ route('products.photo', ['product' => $product, 'photo' => $galleryPhoto]) }}" alt="Foto extra de {{ $product->name }}" class="size-20 rounded-xl border border-slate-200 object-cover group-has-checked:opacity-40">
                            <span class="absolute inset-x-1 bottom-1 flex items-center justify-center gap-1 rounded-lg bg-white/90 px-1 py-0.5 text-[11px] font-bold text-red-700">
                                <input type="checkbox" name="remove_gallery_photos[]" value="{{ $galleryPhoto->id }}" class="size-3 rounded border-slate-300 text-red-600 focus:ring-red-500">
                                Remover
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
            <input id="gallery_photos" name="gallery_photos[]" type="file" multiple accept="image/png,image/jpeg,image/webp"
                class="mt-3 block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-100 file:px-3 file:py-1.5 file:font-semibold file:text-brand-800">
            <x-input-error :messages="$errors->get('gallery_photos')" />
            <x-input-error :messages="collect($errors->get('gallery_photos.*'))->flatten()->unique()->all()" />
        </div>

        <div class="md:col-span-2">
            <label for="description" class="text-sm font-semibold text-slate-700">Descrição</label>
            <textarea id="description" name="description" rows="3" maxlength="500" placeholder="Ex.: Pão brioche, blend 160 g, cheddar e bacon."
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">{{ old('description', $product->description ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('description')" />
        </div>
    </div>
</div>

<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6">
    <a href="{{ route('products.index') }}" class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancelar</a>
    <button type="button" data-clear-form class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">Limpar formulário</button>
    <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">{{ $editing ? 'Salvar alterações' : 'Cadastrar produto' }}</button>
</div>
