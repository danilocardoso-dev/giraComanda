@extends('layouts.app')

@section('title', 'Cardápio digital')

@section('content')
    <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="space-y-6">
            <div class="rounded-3xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">{{ $branch->name }}</p>
                <h2 class="mt-2 text-2xl font-bold text-slate-950">Pedidos pelo QR Code do cartão</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">O cliente aponta a câmera do celular para o QR Code do cartão, vê o cardápio e envia o pedido. A equipe confirma em <a href="{{ route('menu-orders.index') }}" class="font-bold text-brand-700 hover:text-brand-900">Pedidos do cardápio</a> e os itens entram na comanda do cartão.</p>

                <form method="POST" action="{{ route('menu-settings.update') }}" class="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="menu_ordering_enabled" value="0">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="menu_ordering_enabled" value="1" @checked($branch->menu_ordering_enabled) class="size-5 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                        <span>
                            <span class="block text-sm font-bold text-slate-900">Aceitar pedidos pelo celular nesta filial</span>
                            <span class="block text-xs text-slate-500">Desativado, o cliente continua vendo o cardápio, mas pede ao atendente.</span>
                        </span>
                    </label>
                    <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Salvar</button>
                </form>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-brand-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Produtos no cardápio</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $productsOnMenuCount }}</p>
                    </div>
                    <div class="rounded-2xl bg-brand-50 p-4 sm:col-span-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Como publicar um produto</p>
                        <p class="mt-1 text-sm text-slate-700">Em <a href="{{ route('products.index') }}" class="font-bold text-brand-700 hover:text-brand-900">Produtos e estoque</a>, edite o produto e marque “Mostrar no cardápio digital”. Foto e descrição são opcionais.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-bold text-slate-950">Categorias</h2>
                <p class="mt-1 text-sm text-slate-600">Organizam o cardápio na ordem informada. Produtos sem categoria aparecem em “Outros”.</p>

                <x-input-error :messages="$errors->get('name')" />
                <x-input-error :messages="$errors->get('position')" />

                <div class="mt-5 space-y-3">
                    @forelse ($categories as $category)
                        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 sm:flex-row sm:items-end">
                            <form method="POST" action="{{ route('menu-categories.update', $category) }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                                @csrf
                                @method('PUT')
                                <label class="block flex-1">
                                    <span class="text-xs font-semibold text-slate-600">Nome · {{ $category->products_count }} {{ $category->products_count === 1 ? 'produto' : 'produtos' }}</span>
                                    <input name="name" value="{{ $category->name }}" required maxlength="80" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                                </label>
                                <label class="block sm:w-24">
                                    <span class="text-xs font-semibold text-slate-600">Ordem</span>
                                    <input name="position" type="number" min="0" max="999" value="{{ $category->position }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                                </label>
                                <button type="submit" class="rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800">Salvar</button>
                            </form>
                            <form method="POST" action="{{ route('menu-categories.destroy', $category) }}" onsubmit="return confirm('Remover esta categoria? Os produtos dela vão para “Outros”.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-50">Remover</button>
                            </form>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 px-5 py-6 text-center text-sm text-slate-500">Nenhuma categoria criada ainda.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <form method="POST" action="{{ route('menu-categories.store') }}" class="rounded-3xl border border-brand-100 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-bold text-slate-950">Nova categoria</h2>
                <label class="mt-4 block">
                    <span class="text-sm font-semibold text-slate-700">Nome</span>
                    <input name="name" required maxlength="80" placeholder="Ex.: Lanches" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                </label>
                <label class="mt-4 block">
                    <span class="text-sm font-semibold text-slate-700">Ordem de exibição</span>
                    <input name="position" type="number" min="0" max="999" value="{{ $categories->max('position') + 1 }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                </label>
                <button type="submit" class="mt-5 w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Criar categoria</button>
            </form>

            <div class="rounded-3xl bg-brand-900 p-6 text-white">
                <h2 class="text-lg font-bold">Cartões impressos</h2>
                <p class="mt-2 text-sm leading-6 text-brand-100">O QR Code dos cartões abre o cardápio no celular do cliente e continua sendo lido normalmente pela câmera da equipe. Cartões impressos antes desta versão continuam funcionando na equipe; reimprima-os para que o cliente também consiga pedir.</p>
                <a href="{{ route('cards.index') }}" class="mt-4 inline-flex rounded-xl bg-warm-500 px-4 py-2.5 text-sm font-black text-brand-950 hover:bg-warm-400">Ir para cartões</a>
            </div>
        </aside>
    </section>
@endsection
