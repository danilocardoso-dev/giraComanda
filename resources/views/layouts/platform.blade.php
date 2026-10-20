<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Plataforma') · {{ config('app.name') }}</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="min-h-screen lg:grid lg:grid-cols-[270px_1fr]">
        <aside class="hidden min-h-screen border-r border-white/10 bg-slate-950 px-5 py-6 lg:flex lg:flex-col">
            <a href="{{ route('platform.dashboard') }}" class="mb-10 flex items-center gap-3 px-2">
                <div class="grid size-11 place-items-center rounded-2xl bg-warm-500 text-lg font-black text-slate-950">G</div>
                <div>
                    <div class="font-bold tracking-tight text-white">GiraComanda</div>
                    <div class="text-xs text-amber-300">Administração da plataforma</div>
                </div>
            </a>

            <nav class="flex flex-1 flex-col gap-1">
                <a href="{{ route('platform.dashboard') }}" @class(['rounded-xl px-4 py-3 text-sm font-bold', 'bg-white text-slate-950' => request()->routeIs('platform.dashboard'), 'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('platform.dashboard')])>Visão geral</a>
                <a href="{{ route('platform.companies.index') }}" @class(['rounded-xl px-4 py-3 text-sm font-bold', 'bg-white text-slate-950' => request()->routeIs('platform.companies.*', 'platform.subscriptions.*', 'platform.invoices.*', 'platform.owners.*'), 'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('platform.companies.*', 'platform.subscriptions.*', 'platform.invoices.*', 'platform.owners.*')])>Clientes e assinaturas</a>
                <a href="{{ route('platform.backups.index') }}" @class(['rounded-xl px-4 py-3 text-sm font-bold', 'bg-white text-slate-950' => request()->routeIs('platform.backups.*'), 'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('platform.backups.*')])>Backups</a>
                <a href="{{ route('platform.payment-settings.edit') }}" @class(['rounded-xl px-4 py-3 text-sm font-bold', 'bg-white text-slate-950' => request()->routeIs('platform.payment-settings.*'), 'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs('platform.payment-settings.*')])>Configurações</a>
            </nav>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-sm font-bold text-white">{{ auth()->user()->name }}</p>
                <p class="mt-1 text-xs text-slate-400">Administrador da plataforma</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="text-sm font-bold text-amber-300 hover:text-amber-200">Sair do sistema</button>
                </form>
                <div class="mt-4 flex gap-3 text-[11px] text-slate-400">
                    <a href="{{ route('privacy') }}" class="hover:text-white">Privacidade</a>
                    <a href="{{ route('terms') }}" class="hover:text-white">Termos</a>
                </div>
            </div>
        </aside>

        <div class="min-w-0 bg-slate-100 text-slate-800">
            <header class="border-b border-slate-200 bg-white px-5 py-4 sm:px-8 lg:px-10">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-600">Painel central</p>
                        <h1 class="mt-1 text-xl font-bold text-slate-950">@yield('title', 'Plataforma')</h1>
                    </div>
                    <details class="relative lg:hidden">
                        <summary class="cursor-pointer list-none rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Menu</summary>
                        <div class="absolute right-0 z-30 mt-2 w-64 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                            <nav class="flex flex-col gap-1 text-sm font-semibold text-slate-700">
                                <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('platform.dashboard') }}">Visão geral</a>
                                <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('platform.companies.index') }}">Clientes e assinaturas</a>
                                <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('platform.backups.index') }}">Backups</a>
                                <a class="rounded-lg px-3 py-2 hover:bg-slate-100" href="{{ route('platform.payment-settings.edit') }}">Configurações</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-red-700 hover:bg-red-50">Sair</button>
                                </form>
                            </nav>
                        </div>
                    </details>
                </div>
            </header>

            <main class="px-5 py-7 sm:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <x-flash-message />
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
