@extends('layouts.app')

@section('title', 'Estoque de '.$product->name)

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('products.index') }}" class="text-sm font-bold text-brand-700 hover:text-brand-900">← Voltar para produtos</a>
            <h2 class="mt-3 text-2xl font-bold text-slate-950">{{ $product->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">Histórico completo das entradas e saídas deste produto.</p>
        </div>
        <div @class([
            'rounded-2xl px-6 py-4 text-white',
            'bg-amber-600' => $product->stock_milliunits !== null && $product->min_stock_milliunits !== null && $product->stock_milliunits <= $product->min_stock_milliunits,
            'bg-brand-900' => ! ($product->stock_milliunits !== null && $product->min_stock_milliunits !== null && $product->stock_milliunits <= $product->min_stock_milliunits),
        ])>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/75">Saldo atual</p>
            <p class="mt-1 text-3xl font-black">
                @if ($product->stock_milliunits === null)
                    Não controlado
                @else
                    {{ number_format($product->stock_milliunits / 1000, $product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $product->sale_type->quantityLabel() }}
                @endif
            </p>
            @if ($product->min_stock_milliunits !== null)
                <p class="mt-1 text-xs text-white/80">Alerta em {{ number_format($product->min_stock_milliunits / 1000, $product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $product->sale_type->quantityLabel() }}</p>
            @endif
        </div>
    </div>

    @if ($product->stock_milliunits === null)
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <p class="font-bold">O controle de estoque ainda não está ativo.</p>
            <p class="mt-1">Registre um ajuste de inventário com o saldo contado para começar.</p>
        </div>
    @endif

    <section class="mt-7 rounded-2xl border border-brand-100 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-bold text-slate-950">Registrar movimento manual</h3>
        <p class="mt-1 text-sm text-slate-500">Na entrada e saída, informe a quantidade movimentada. No ajuste, informe o novo saldo total contado.</p>
        <form method="POST" action="{{ route('products.stock-movements.store', $product) }}" class="mt-5 grid gap-4 lg:grid-cols-[220px_180px_minmax(0,1fr)_auto] lg:items-start">
            @csrf
            <div>
                <label for="type" class="text-sm font-semibold text-slate-700">Tipo</label>
                <select id="type" name="type" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                    @foreach ($movementTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" />
            </div>
            <div>
                <label for="quantity" class="text-sm font-semibold text-slate-700">Quantidade / saldo</label>
                <div class="mt-2 flex rounded-xl border border-slate-300 bg-white focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-500/20">
                    <input id="quantity" name="quantity" type="number" min="0" step="{{ $product->sale_type === \App\ProductSaleType::Weight ? '0.001' : '1' }}" value="{{ old('quantity') }}" required class="min-w-0 flex-1 rounded-l-xl px-4 py-3 outline-none">
                    <span class="border-l border-slate-200 px-3 py-3 text-sm text-slate-500">{{ $product->sale_type->quantityLabel() }}</span>
                </div>
                <x-input-error :messages="$errors->get('quantity')" />
            </div>
            <div>
                <label for="description" class="text-sm font-semibold text-slate-700">Motivo ou referência</label>
                <input id="description" name="description" value="{{ old('description') }}" maxlength="255" placeholder="Ex.: compra fornecedor, perda ou contagem" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                <x-input-error :messages="$errors->get('description')" />
            </div>
            <button class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Registrar</button>
        </form>
    </section>

    <div class="mt-7 flex items-end justify-between gap-4">
        <div><h3 class="text-xl font-bold text-slate-950">Histórico de movimentos</h3><p class="mt-1 text-sm text-slate-500">Vendas e cancelamentos de itens aparecem automaticamente.</p></div>
        <span class="text-sm font-semibold text-slate-500">{{ $movements->total() }} {{ $movements->total() === 1 ? 'movimento' : 'movimentos' }}</span>
    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800"><tr><th class="px-5 py-4">Data</th><th class="px-5 py-4">Movimento</th><th class="px-5 py-4">Responsável</th><th class="px-5 py-4">Referência</th><th class="px-5 py-4 text-right">Quantidade</th><th class="px-5 py-4 text-right">Saldo</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr class="hover:bg-brand-50/60">
                            <td class="px-5 py-4 text-slate-600">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $movement->type->label() }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $movement->recorder->name }}</td>
                            <td class="px-5 py-4 text-slate-600">
                                @if ($movement->tab)
                                    <a href="{{ route('tabs.show', $movement->tab) }}" class="font-bold text-brand-700 hover:text-brand-900">{{ $movement->tab->card->code }}</a>
                                @endif
                                @if ($movement->description)
                                    <span class="{{ $movement->tab ? 'mt-1 block text-xs' : '' }}">{{ $movement->description }}</span>
                                @endif
                            </td>
                            <td @class(['px-5 py-4 text-right font-black', 'text-emerald-700' => $movement->quantity_delta_milliunits >= 0, 'text-red-700' => $movement->quantity_delta_milliunits < 0])>
                                {{ $movement->quantity_delta_milliunits > 0 ? '+' : '' }}{{ number_format($movement->quantity_delta_milliunits / 1000, $product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $product->sale_type->quantityLabel() }}
                            </td>
                            <td class="px-5 py-4 text-right font-bold text-slate-900">{{ number_format($movement->balance_after_milliunits / 1000, $product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $product->sale_type->quantityLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Nenhuma movimentação registrada. O saldo atual foi cadastrado antes do início deste histórico.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-6">{{ $movements->links() }}</div>
@endsection
