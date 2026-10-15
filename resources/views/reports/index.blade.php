@extends('layouts.app')

@section('title', 'Relatórios gerenciais')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Resumo do período</h2>
            <p class="mt-1 text-sm text-slate-600">Resultados de {{ $periodLabel }} · {{ $reportScopeLabel }}. “Falta receber” mostra a dívida atual, não apenas o movimento do período.</p>
        </div>
        <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-xl border border-brand-200 bg-white px-5 py-3 text-sm font-bold text-brand-800 hover:bg-brand-50 print:hidden">Imprimir relatório</button>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="mt-6 grid gap-4 rounded-2xl border border-brand-100 bg-white p-5 shadow-sm lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end print:hidden">
        <div>
            <label for="start_date" class="block text-sm font-bold text-slate-700">Data inicial</label>
            <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $startDate) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
            @error('start_date') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="end_date" class="block text-sm font-bold text-slate-700">Data final</label>
            <input id="end_date" name="end_date" type="date" value="{{ old('end_date', $endDate) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
            @error('end_date') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="branch_id" class="block text-sm font-bold text-slate-700">Filial</label>
            <select id="branch_id" name="branch_id" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
                @if ($canViewCompanyReport)
                    <option value="all" @selected(old('branch_id', $branchSelection) === 'all')>Todas as filiais</option>
                @endif
                @foreach ($reportBranches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $branchSelection) === (string) $branch->id)>
                        {{ $branch->name }}{{ $branch->active ? '' : ' (inativa)' }}
                    </option>
                @endforeach
            </select>
            @error('branch_id') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Atualizar período</button>
    </form>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Vendas brutas</p>
            <p class="mt-3 text-3xl font-bold text-brand-900">R$ {{ number_format($grossSalesCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ $closedTabsCount }} {{ $closedTabsCount === 1 ? 'comanda fechada' : 'comandas fechadas' }}</p>
        </article>
        <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Ticket médio</p>
            <p class="mt-3 text-3xl font-bold text-slate-950">R$ {{ number_format($averageTicketCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-slate-500">Média por comanda fechada</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-emerald-800">Total recebido</p>
            <p class="mt-3 text-3xl font-bold text-emerald-950">R$ {{ number_format($receivedTotalCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-emerald-800">Pagamentos registrados no período</p>
        </article>
        <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-amber-800">Anotações criadas</p>
            <p class="mt-3 text-3xl font-bold text-amber-950">R$ {{ number_format($annotationsTotalCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-amber-800">Novos valores anotados no período</p>
        </article>
        <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm sm:col-span-2">
            <p class="text-sm font-semibold text-red-800">Falta receber hoje</p>
            <p class="mt-3 text-3xl font-bold text-red-950">R$ {{ number_format($openReceivablesCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-sm text-red-800">Tudo que os clientes ainda devem nesta seleção</p>
        </article>
    </section>

    <section class="mt-6 rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-bold text-slate-950">Recebimentos por forma de pagamento</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($paymentMethods as $method)
                <div class="rounded-xl bg-brand-50 p-4">
                    <p class="text-sm font-semibold text-slate-600">{{ $method->label() }}</p>
                    <p class="mt-2 text-xl font-bold text-brand-900">R$ {{ number_format(((int) ($totalsByPaymentMethod[$method->value] ?? 0)) / 100, 2, ',', '.') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-950">Vendas por dia</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800"><tr><th class="px-5 py-3">Data</th><th class="px-5 py-3">Comandas</th><th class="px-5 py-3 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($dailySales as $day)
                            <tr><td class="px-5 py-4 font-semibold text-slate-900">{{ \Carbon\CarbonImmutable::parse($day->sale_date)->format('d/m/Y') }}</td><td class="px-5 py-4 text-slate-600">{{ $day->tabs_count }}</td><td class="px-5 py-4 text-right font-bold text-brand-900">R$ {{ number_format($day->total_cents / 100, 2, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-10 text-center text-slate-500">Nenhuma venda no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-950">Produtos mais vendidos</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800"><tr><th class="px-5 py-3">Produto</th><th class="px-5 py-3">Quantidade</th><th class="px-5 py-3 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($topProducts as $product)
                            <tr>
                                <td class="px-5 py-4 font-semibold text-slate-900">{{ $product->product_name }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ number_format($product->quantity_milliunits / 1000, $product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $product->sale_type->quantityLabel() }}</td>
                                <td class="px-5 py-4 text-right font-bold text-brand-900">R$ {{ number_format($product->total_cents / 100, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-10 text-center text-slate-500">Nenhum produto vendido no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4"><div><h3 class="font-bold text-amber-950">Produtos com estoque baixo</h3><p class="mt-1 text-sm text-amber-800">Itens ativos que atingiram ou ficaram abaixo do estoque mínimo.</p></div><span class="rounded-full bg-amber-200 px-3 py-1 text-xs font-black text-amber-900">{{ $lowStockProducts->count() }}</span></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($lowStockProducts as $stockConfiguration)
                <div class="rounded-xl border border-amber-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-bold text-slate-950">{{ $stockConfiguration->product->name }}</p>
                        @if ($selectedBranch === null)
                            <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-900">{{ $stockConfiguration->branch->name }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-600">Atual: {{ number_format($stockConfiguration->stock_milliunits / 1000, $stockConfiguration->product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $stockConfiguration->product->sale_type->quantityLabel() }} · Mínimo: {{ number_format($stockConfiguration->min_stock_milliunits / 1000, $stockConfiguration->product->sale_type === \App\ProductSaleType::Weight ? 3 : 0, ',', '.') }} {{ $stockConfiguration->product->sale_type->quantityLabel() }}</p>
                    @if ($selectedBranch?->is($currentBranch))
                        <a href="{{ route('products.stock.show', $stockConfiguration->product) }}" class="mt-3 inline-flex text-sm font-bold text-amber-900 hover:text-amber-700 print:hidden">Ajustar estoque</a>
                    @endif
                </div>
            @empty
                <p class="text-sm font-semibold text-emerald-800">Nenhum produto com estoque baixo.</p>
            @endforelse
        </div>
    </section>
@endsection
