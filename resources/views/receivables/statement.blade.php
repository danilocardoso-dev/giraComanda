<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resumo de {{ $period_label }} · {{ $customer->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page { margin: 12mm; }
            body { background: white !important; }
            .print-card { box-shadow: none !important; }
            .consumption-entry { break-inside: avoid; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900 sm:px-8 print:bg-white print:p-0">
    <main class="print-card mx-auto max-w-4xl rounded-3xl bg-white p-6 shadow-xl sm:p-10 print:max-w-none print:rounded-none print:p-0 print:shadow-none">
        <header class="flex flex-col gap-5 border-b border-slate-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                @if ($settings->logo_path)
                    <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-2xl border border-brand-100 bg-white p-1.5">
                        <img src="{{ route('branding.logo', ['v' => $settings->updated_at?->timestamp]) }}" alt="Logo de {{ $settings->name }}" class="size-full object-contain">
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-600">Resumo de consumo</p>
                    <h1 class="mt-1 text-2xl font-black text-brand-950 sm:text-3xl">{{ $settings->name }}</h1>
                    @if ($settings->phone)
                        <p class="mt-1 text-sm text-slate-500">{{ $settings->phone }}</p>
                    @endif
                </div>
            </div>
            <button type="button" onclick="window.print()" class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800 print:hidden">Salvar ou imprimir PDF</button>
        </header>

        <section class="mt-6 grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
            <div>
                <p class="text-sm font-semibold text-slate-500">Cliente</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $customer->name }}</h2>
                <p class="mt-1 text-sm text-slate-600">CPF {{ $customer->formatted_cpf }}</p>
            </div>
            <div class="sm:text-right">
                <p class="text-sm font-semibold text-slate-500">Período do resumo</p>
                <p class="mt-1 text-lg font-black capitalize text-brand-900">{{ $period_label }}</p>
            </div>
        </section>

        <section class="mt-6 rounded-3xl bg-brand-900 p-6 text-white print:border-2 print:border-brand-900 print:bg-white print:text-brand-950">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-200 print:text-brand-700">Valor devedor</p>
            <p class="mt-2 text-4xl font-black tracking-tight">R$ {{ number_format($closing_balance_cents / 100, 2, ',', '.') }}</p>
            <p class="mt-3 text-sm leading-6 text-brand-100 print:text-slate-600">
                Saldo anterior de <strong>R$ {{ number_format($opening_balance_cents / 100, 2, ',', '.') }}</strong>
                + consumos de <strong>R$ {{ number_format($charges_total_cents / 100, 2, ',', '.') }}</strong>
                − pagamentos de <strong>R$ {{ number_format($payments_total_cents / 100, 2, ',', '.') }}</strong>.
            </p>
        </section>

        <section class="mt-8">
            <div>
                <h3 class="text-xl font-black text-slate-950">O que foi consumido</h3>
                <p class="mt-1 text-sm text-slate-500">Produtos anotados para {{ $customer->name }} durante o período.</p>
            </div>

            <div class="mt-4 grid gap-4">
                @forelse ($receivables as $receivable)
                    @php
                        $consumedAt = $receivable->tab?->closed_at ?? $receivable->created_at;
                        $items = $receivable->tab?->items
                            ?->filter(function ($item) use ($customer, $receivable): bool {
                                if ($item->shares->isEmpty()) {
                                    return $receivable->tab?->customer_id === $customer->id;
                                }

                                return $item->shares->contains('customer_id', $customer->id);
                            }) ?? collect();
                    @endphp

                    <article class="consumption-entry overflow-hidden rounded-2xl border border-slate-200">
                        <div class="flex flex-wrap items-center justify-between gap-3 bg-brand-50 px-4 py-3">
                            <div>
                                <p class="font-black text-brand-950">{{ $consumedAt->format('d/m/Y') }} às {{ $consumedAt->format('H:i') }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">Vencimento: {{ $receivable->due_date->format('d/m/Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-brand-600">Total deste consumo</p>
                                <p class="text-lg font-black text-brand-950">R$ {{ number_format($receivable->amount_cents / 100, 2, ',', '.') }}</p>
                            </div>
                        </div>

                        @if ($items->isEmpty())
                            <div class="px-4 py-5 text-sm text-slate-500">Os produtos deste consumo não estão disponíveis para detalhamento.</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-white text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-2.5">Produto</th>
                                            <th class="px-4 py-2.5">Quantidade</th>
                                            <th class="px-4 py-2.5 text-right">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($items as $item)
                                            @php
                                                $share = $item->shares->firstWhere('customer_id', $customer->id);
                                                $amountCents = $share?->amount_cents ?? $item->total_cents;
                                                $quantity = $item->sale_type === \App\ProductSaleType::Weight
                                                    ? number_format($item->quantity_milliunits / 1000, 3, ',', '.').' kg'
                                                    : intdiv($item->quantity_milliunits, 1000).' un.';
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <p class="font-bold text-slate-950">{{ $item->product_name }}</p>
                                                    @if ($share)
                                                        <p class="mt-0.5 text-xs text-slate-500">Parte do cliente no rateio entre {{ $item->shares->count() }} pessoas</p>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $quantity }}</td>
                                                <td class="whitespace-nowrap px-4 py-3 text-right font-black text-brand-900">R$ {{ number_format($amountCents / 100, 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">Nenhum consumo anotado neste período.</div>
                @endforelse
            </div>
        </section>

        <footer class="mt-8 border-t border-slate-200 pt-4 text-xs text-slate-500">
            Resumo emitido em {{ now()->format('d/m/Y H:i') }}. Este documento não é fiscal.
        </footer>
    </main>
</body>
</html>
