<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Entrar') · {{ config('app.name') }}</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <main class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden overflow-hidden bg-brand-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-28 -top-28 size-96 rounded-full border-[64px] border-warm-500/20"></div>
            <div class="absolute -bottom-24 left-20 size-72 rounded-full border-[54px] border-brand-500/30"></div>

            <div class="relative flex items-center gap-3">
                <div class="grid size-12 place-items-center rounded-2xl bg-warm-500 text-xl font-black text-brand-950">G</div>
                <span class="text-2xl font-bold tracking-tight">GiraComanda</span>
            </div>

            <div class="relative max-w-xl">
                <p class="mb-5 text-sm font-bold uppercase tracking-[0.24em] text-warm-400">Operação simples, contas claras</p>
                <h1 class="text-5xl font-bold leading-tight">Do consumo ao fechamento, tudo certo.</h1>
                <p class="mt-6 max-w-lg text-lg leading-8 text-brand-100">Comandas, clientes, caixa e estoque organizados em um único lugar.</p>
            </div>

            <p class="relative text-sm text-brand-200">Ambiente seguro para a equipe do estabelecimento.</p>
        </section>

        <section class="flex items-center justify-center px-6 py-12 sm:px-10">
            <div class="w-full @yield('container-width', 'max-w-md')">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <div class="grid size-11 place-items-center rounded-2xl bg-brand-700 text-lg font-black text-white">G</div>
                    <span class="text-xl font-bold text-brand-900">GiraComanda</span>
                </div>

                @yield('content')
                <div class="mt-10 flex justify-center gap-4 text-xs text-slate-500">
                    <a href="{{ route('privacy') }}" class="hover:text-brand-700">Privacidade</a>
                    <a href="{{ route('terms') }}" class="hover:text-brand-700">Termos de uso</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
