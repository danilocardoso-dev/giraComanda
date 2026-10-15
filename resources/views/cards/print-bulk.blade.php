<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <x-favicon />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir {{ $cards->count() }} {{ $cards->count() === 1 ? 'cartão' : 'cartões' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        .print-card {
            width: 85.6mm;
            height: 54mm;
            break-inside: avoid;
        }

        @media print {
            body {
                background: white !important;
            }

            .print-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 85.6mm);
                justify-content: start;
                gap: 6mm;
            }

            .print-card {
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-brand-50 text-slate-950">
    <main class="mx-auto min-h-screen max-w-7xl px-5 py-10 sm:px-8 print:min-h-0 print:max-w-none print:p-0">
        <div class="mx-auto mb-8 flex max-w-5xl flex-wrap items-center justify-between gap-4 print:hidden">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Impressão em lote</p>
                <h1 class="mt-1 text-2xl font-bold">{{ $cards->count() }} {{ $cards->count() === 1 ? 'cartão selecionado' : 'cartões selecionados' }}</h1>
                <p class="mt-1 text-sm text-slate-600">Os cartões serão distribuídos em duas colunas por folha A4.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('cards.index', ['status' => 'all']) }}" class="rounded-xl bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">Voltar</a>
                <button type="button" onclick="window.print()" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-800">
                    Imprimir {{ $cards->count() }} {{ $cards->count() === 1 ? 'cartão' : 'cartões' }}
                </button>
            </div>
        </div>

        <div class="print-grid flex flex-wrap justify-center gap-6 print:justify-start">
            @foreach ($cards as $card)
                <x-printable-card
                    :card="$card"
                    :qr-code-data-uri="$qrCodeDataUris->get($card->getKey())"
                    :settings="$establishmentSettings"
                    :company="$currentCompany"
                />
            @endforeach
        </div>
    </main>
</body>
</html>
