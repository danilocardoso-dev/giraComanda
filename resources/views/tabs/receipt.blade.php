<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprovante {{ $tab->card->code }} · {{ $settings->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page { size: 80mm auto; margin: 4mm; }
            body { background: white !important; }
            .receipt { width: 72mm !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 px-3 py-6 text-slate-950 print:bg-white print:p-0">
    <main class="receipt mx-auto w-full max-w-[80mm] bg-white p-5 font-mono text-[12px] leading-5 shadow-xl print:p-0 print:shadow-none">
        <header class="border-b border-dashed border-slate-400 pb-4 text-center">
            <h1 class="text-lg font-black uppercase">{{ $settings->name }}</h1>
            @if ($settings->document)
                <p class="mt-1">{{ $settings->document }}</p>
            @endif
            @if ($settings->address)
                <p>{{ $settings->address }}</p>
            @endif
            @if ($settings->phone)
                <p>{{ $settings->phone }}</p>
            @endif
            <p class="mt-3 font-bold uppercase tracking-wider">Comprovante não fiscal</p>
        </header>

        <section class="border-b border-dashed border-slate-400 py-4">
            <div class="flex justify-between gap-3"><span>Comanda</span><strong>{{ $tab->card->code }}</strong></div>
            <div class="flex justify-between gap-3"><span>Abertura</span><span>{{ $tab->opened_at->format('d/m/Y H:i') }}</span></div>
            <div class="flex justify-between gap-3"><span>Fechamento</span><span>{{ $tab->closed_at->format('d/m/Y H:i') }}</span></div>
            @if ($tab->customer)
                <div class="mt-1 flex justify-between gap-3"><span>Cliente</span><strong class="text-right">{{ $tab->customer->name }}</strong></div>
            @endif
        </section>

        <section class="border-b border-dashed border-slate-400 py-4">
            <div class="mb-2 grid grid-cols-[1fr_auto] gap-3 font-bold uppercase"><span>Itens</span><span>Total</span></div>
            <div class="grid gap-3">
                @foreach ($tab->items as $item)
                    <div>
                        <div class="grid grid-cols-[1fr_auto] gap-3"><span class="font-semibold">{{ $item->product_name }}</span><span class="font-bold">R$ {{ number_format($item->total_cents / 100, 2, ',', '.') }}</span></div>
                        <p class="text-slate-600">
                            @if ($item->sale_type === \App\ProductSaleType::Weight)
                                {{ number_format($item->quantity_milliunits / 1000, 3, ',', '.') }} kg
                            @else
                                {{ intdiv($item->quantity_milliunits, 1000) }} un.
                            @endif
                            × R$ {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }}
                        </p>
                        @if ($item->shares->isNotEmpty())
                            <div class="mt-1 border-l-2 border-slate-300 pl-2 text-[11px] text-slate-600">
                                @foreach ($item->shares as $share)
                                    <div class="flex justify-between gap-2"><span>{{ $share->customer->name }}</span><span>R$ {{ number_format($share->amount_cents / 100, 2, ',', '.') }}</span></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="border-b border-double border-slate-700 py-4">
            <div class="flex items-end justify-between gap-3 text-base font-black"><span>Total</span><span>R$ {{ number_format($tab->total_cents / 100, 2, ',', '.') }}</span></div>
        </section>

        <section class="border-b border-dashed border-slate-400 py-4">
            @if ($tab->payments->isNotEmpty())
                <p class="mb-2 font-bold uppercase">Pagamento</p>
                @foreach ($tab->payments as $payment)
                    <div class="flex justify-between gap-3"><span>{{ $payment->method->label() }}</span><span>R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</span></div>
                @endforeach
            @elseif ($tab->receivables->isNotEmpty())
                <p class="mb-2 font-bold uppercase">Anotado para pagamento posterior</p>
                @foreach ($tab->receivables as $receivable)
                    <div class="mb-2">
                        <div class="flex justify-between gap-3"><span>{{ $receivable->customer->name }}</span><span class="font-bold">R$ {{ number_format($receivable->amount_cents / 100, 2, ',', '.') }}</span></div>
                        <p class="text-[11px] text-slate-600">Vencimento: {{ $receivable->due_date->format('d/m/Y') }}</p>
                    </div>
                @endforeach
            @endif
        </section>

        <footer class="pt-4 text-center">
            <p>Atendido por {{ $tab->closer->name }}</p>
            <p class="mt-3 font-bold uppercase">Não é documento fiscal</p>
            <p class="mt-1 text-slate-500">Obrigado pela preferência.</p>
            <button type="button" onclick="window.print()" class="mt-5 w-full rounded-lg bg-slate-900 px-4 py-2.5 font-sans text-sm font-bold text-white print:hidden">Imprimir comprovante</button>
        </footer>
    </main>
</body>
</html>
