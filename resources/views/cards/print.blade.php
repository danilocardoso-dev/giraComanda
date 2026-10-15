<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <x-favicon />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir {{ $card->code }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        .print-card {
            width: 85.6mm;
            height: 54mm;
        }

        @media print {
            body {
                background: white !important;
            }

            .print-card {
                box-shadow: none !important;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-brand-50 text-slate-950">
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col items-center px-5 py-10 sm:px-8 print:block print:min-h-0 print:max-w-none print:p-0">
        <div class="mb-8 flex w-full max-w-3xl flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Cartão reutilizável</p>
                <h1 class="mt-1 text-2xl font-bold">Imprimir {{ $card->code }}</h1>
                <p class="mt-1 text-sm text-slate-600">Use papel firme, recorte e plastifique. O cartão não pertence a um cliente específico e pode ser reutilizado.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('cards.index', ['status' => 'all']) }}" class="rounded-xl bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">Voltar</a>
                <button type="button" onclick="window.print()" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-800">
                    Imprimir cartão
                </button>
            </div>
        </div>

        <x-printable-card
            :card="$card"
            :qr-code-data-uri="$qrCodeDataUri"
            :settings="$establishmentSettings"
            :company="$currentCompany"
        />

        @if ($card->status === \App\CardStatus::Blocked)
            <p class="mt-4 rounded-xl bg-red-100 px-4 py-2 text-sm font-bold text-red-800 print:hidden">Este cartão está inativo. O QR Code pode ser impresso, mas não deve aceitar novos consumos.</p>
        @endif

        <p class="mt-5 text-center text-xs text-slate-500 print:hidden">O contorno tracejado indica a área de recorte no tamanho aproximado de 85,6 × 54 mm.</p>
    </main>
</body>
</html>
