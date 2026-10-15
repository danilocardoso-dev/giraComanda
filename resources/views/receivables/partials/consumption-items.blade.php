@php
    $items = $receivable->tab?->items
        ?->filter(function ($item) use ($customer, $receivable): bool {
            if ($item->shares->isEmpty()) {
                return $receivable->tab?->customer_id === $customer->id;
            }

            return $item->shares->contains('customer_id', $customer->id);
        }) ?? collect();
@endphp

@if ($items->isEmpty())
    <p class="text-xs text-slate-500">Detalhes dos itens não disponíveis para esta anotação.</p>
@else
    <div class="grid gap-2">
        @foreach ($items as $item)
            @php
                $share = $item->shares->firstWhere('customer_id', $customer->id);
                $amountCents = $share?->amount_cents ?? $item->total_cents;
                $quantity = $item->sale_type === \App\ProductSaleType::Weight
                    ? number_format($item->quantity_milliunits / 1000, 3, ',', '.').' kg'
                    : intdiv($item->quantity_milliunits, 1000).' un.';
            @endphp
            <div class="flex flex-col gap-1 rounded-xl bg-white px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-slate-900">{{ $item->product_name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $quantity }} × R$ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}
                        @if ($share)
                            · Rateio entre {{ $item->shares->count() }} clientes
                        @endif
                    </p>
                </div>
                <div class="shrink-0 sm:text-right">
                    @if ($share)
                        <p class="text-[11px] font-bold uppercase tracking-wide text-brand-600">Sua parte</p>
                    @endif
                    <p class="font-black text-brand-900">R$ {{ number_format($amountCents / 100, 2, ',', '.') }}</p>
                </div>
            </div>
        @endforeach
    </div>
@endif
