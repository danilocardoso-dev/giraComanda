@extends('layouts.app')

@section('title', 'Produtos e estoque')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Produtos</h2>
            <p class="mt-1 text-sm text-slate-600">Acompanhe saldos, entradas, vendas, ajustes e alertas de reposição.</p>
        </div>
        <a href="{{ route('products.create') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Novo produto</a>
    </div>

    @if ($lowStockCount > 0)
        <div class="mt-6 flex items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900">
            <div><p class="font-bold">{{ $lowStockCount }} {{ $lowStockCount === 1 ? 'produto atingiu' : 'produtos atingiram' }} o estoque mínimo</p><p class="mt-1 text-sm text-amber-800">Os itens destacados precisam de reposição ou conferência.</p></div>
            <span class="rounded-full bg-amber-200 px-3 py-1 text-xs font-black uppercase tracking-wider">Atenção</span>
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr>
                        <th class="px-5 py-4">Produto</th>
                        <th class="px-5 py-4">Venda</th>
                        <th class="px-5 py-4">Preço</th>
                        <th class="px-5 py-4">Estoque</th>
                        <th class="px-5 py-4">Situação</th>
                        <th class="px-5 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        @php
                            $isLowStock = $product->active
                                && $product->stock_milliunits !== null
                                && $product->min_stock_milliunits !== null
                                && $product->stock_milliunits <= $product->min_stock_milliunits;
                        @endphp
                        <tr class="hover:bg-brand-50/60">
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ $product->name }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $product->sale_type->label() }}</td>
                            <td class="px-5 py-4 text-slate-600">R$ {{ number_format($product->price_cents / 100, 2, ',', '.') }} / {{ $product->sale_type->quantityLabel() }}</td>
                            <td class="px-5 py-4 text-slate-600">
                                @if ($product->stock_milliunits === null)
                                    Não controlado
                                @elseif ($product->sale_type === \App\ProductSaleType::Weight)
                                    {{ number_format($product->stock_milliunits / 1000, 3, ',', '.') }} kg
                                @else
                                    {{ intdiv($product->stock_milliunits, 1000) }} un.
                                @endif
                                @if ($isLowStock)
                                    <span class="mt-1 block text-xs font-bold text-amber-700">Estoque baixo</span>
                                @elseif ($product->stock_milliunits !== null && $product->min_stock_milliunits !== null)
                                    <span class="mt-1 block text-xs text-slate-400">Mínimo: {{ number_format($product->min_stock_milliunits / 1000, $product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $product->sale_type->quantityLabel() }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-100 text-emerald-800' => $product->active,
                                    'bg-slate-200 text-slate-700' => ! $product->active,
                                ])>{{ $product->active ? 'Ativo' : 'Inativo' }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('products.stock.show', $product) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Estoque</a>
                                    <a href="{{ route('products.edit', $product) }}" class="font-bold text-brand-700 hover:text-brand-900">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Nenhum produto cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $products->links() }}</div>
@endsection
