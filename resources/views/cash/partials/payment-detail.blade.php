<div class="flex flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Detalhes do recebimento #{{ $payment->id }}</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">
                {{ $payment->tab ? 'Venda de comanda' : 'Pagamento de anotação' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">{{ $payment->paid_at->format('d/m/Y \à\s H:i') }} · registrado por {{ $payment->recorder->name }}</p>
        </div>
        <button type="button" data-cash-payment-detail-close aria-label="Fechar detalhes" class="grid size-10 shrink-0 place-items-center rounded-full bg-slate-100 text-xl font-bold text-slate-600 hover:bg-slate-200">×</button>
    </div>

    <section class="grid gap-3 sm:grid-cols-3">
        <article class="rounded-2xl border border-brand-100 bg-brand-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-600">Origem</p>
            <p class="mt-2 font-black text-brand-950">{{ $payment->tab ? 'Comanda '.$payment->tab->card->code : 'Anotação do cliente' }}</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Recebido por</p>
            <p class="mt-2 font-black text-emerald-950">{{ $payment->method->label() }}</p>
        </article>
        <article class="rounded-2xl bg-brand-900 p-4 text-white">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-200">Valor recebido</p>
            <p class="mt-2 text-2xl font-black">R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</p>
        </article>
    </section>

    @if ($payment->tab)
        <section>
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h3 class="text-lg font-bold text-slate-950">Itens vendidos</h3>
                    <p class="mt-1 text-sm text-slate-500">Produtos registrados na comanda {{ $payment->tab->card->code }}.</p>
                </div>
                <p class="text-sm font-semibold text-slate-600">{{ $payment->tab->customer?->name ?? 'Cliente não identificado' }}</p>
            </div>

            <div class="mt-3 grid gap-2">
                @forelse ($payment->tab->items as $item)
                    @php
                        $quantity = $item->sale_type === \App\ProductSaleType::Weight
                            ? number_format($item->quantity_milliunits / 1000, 3, ',', '.').' kg'
                            : intdiv($item->quantity_milliunits, 1000).' un.';
                    @endphp
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-brand-100 bg-white px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-900">{{ $item->product_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $quantity }} × R$ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}</p>
                        </div>
                        <p class="shrink-0 font-black text-brand-900">R$ {{ number_format($item->total_cents / 100, 2, ',', '.') }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Os itens desta comanda não estão disponíveis.</div>
                @endforelse
            </div>
        </section>
    @else
        <section>
            <div>
                <h3 class="text-lg font-bold text-slate-950">Anotações pagas</h3>
                <p class="mt-1 text-sm text-slate-500">O valor recebido foi usado nas dívidas abaixo, começando pelas mais antigas.</p>
            </div>

            <div class="mt-3 grid gap-3">
                @forelse ($payment->allocations as $allocation)
                    @php($receivable = $allocation->receivable)
                    @if ($receivable)
                        <article class="rounded-2xl border border-brand-100 bg-brand-50/60 p-4">
                            <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-black text-slate-950">{{ $receivable->tab?->card?->code ?? 'Anotação' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Anotado em {{ $receivable->created_at->format('d/m/Y H:i') }}{{ $receivable->branch ? ' · '.$receivable->branch->name : '' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Baixado neste pagamento</p>
                                    <p class="mt-1 text-lg font-black text-emerald-900">R$ {{ number_format($allocation->amount_cents / 100, 2, ',', '.') }}</p>
                                </div>
                            </div>
                            @include('receivables.partials.consumption-items', ['receivable' => $receivable, 'customer' => $payment->customer])
                        </article>
                    @endif
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Os itens vinculados a este pagamento não estão disponíveis.</div>
                @endforelse
            </div>
        </section>
    @endif

    <div class="flex justify-end border-t border-slate-200 pt-5">
        <button type="button" data-cash-payment-detail-close class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800">Fechar</button>
    </div>
</div>
