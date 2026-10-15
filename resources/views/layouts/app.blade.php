<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    @if ($supportMode ?? false)
        <div class="sticky top-0 z-50 flex flex-col gap-3 bg-amber-300 px-5 py-3 text-slate-950 shadow-md sm:flex-row sm:items-center sm:justify-between lg:px-8">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em]">Modo suporte ativo</p>
                <p class="text-sm font-semibold">Você está acessando {{ $currentCompany->name }} · {{ $currentBranch->name }} como administrador master. Todas as ações são identificadas.</p>
            </div>
            <form method="POST" action="{{ route('platform.support.destroy') }}">
                @csrf
                @method('DELETE')
                <button class="w-full rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800 sm:w-auto">Encerrar suporte</button>
            </form>
        </div>
    @endif
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
        <aside class="hidden min-h-screen bg-brand-900 px-5 py-6 text-white lg:flex lg:flex-col">
            <a href="{{ route('dashboard') }}" class="mb-10 block px-2" aria-label="Ir para a visão geral">
                <x-brand-logo size="sm" inverted />
                <div class="ml-[3.25rem] mt-1 text-xs text-brand-200">{{ $currentCompany->name }}</div>
            </a>

            <div class="mb-6 rounded-2xl border border-white/10 bg-white/8 p-4">
                @if ($establishmentSettings?->logo_path)
                    <div class="mb-4 grid min-h-20 place-items-center overflow-hidden rounded-xl bg-white p-2.5 shadow-inner">
                        <img src="{{ route('branding.logo', ['v' => $establishmentSettings->updated_at?->timestamp]) }}" alt="Logo de {{ $establishmentSettings->name }}" class="max-h-16 max-w-full object-contain drop-shadow-sm">
                    </div>
                @endif
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-brand-300">Filial em uso</p>
                <p class="mt-2 font-bold text-white">{{ $currentBranch->name }}</p>
                @if ($availableBranchesCount > 1 && ! ($supportMode ?? false))
                    <a href="{{ route('branches.index') }}" class="mt-2 inline-block text-xs font-semibold text-warm-400 hover:text-warm-300">Trocar filial</a>
                @endif
            </div>

            <nav class="flex flex-1 flex-col gap-1">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Visão geral</x-nav-link>
                <x-nav-link :href="route('tabs.index')" :active="request()->routeIs('tabs.*')">Comandas</x-nav-link>

                @if (auth()->user()->hasRole(\App\UserRole::Admin, \App\UserRole::Cashier))
                    <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Clientes</x-nav-link>
                    <x-nav-link :href="route('receivables.index')" :active="request()->routeIs('receivables.*')">Contas a receber</x-nav-link>
                    <x-nav-link :href="route('monthly-closing.index')" :active="request()->routeIs('monthly-closing.*')">Fechamento mensal</x-nav-link>
                    <x-nav-link :href="route('cash.index')" :active="request()->routeIs('cash.*')">Movimento do caixa</x-nav-link>
                    <x-nav-link :href="route('cards.index')" :active="request()->routeIs('cards.*')">Cartões</x-nav-link>
                @endif

                @if (auth()->user()->hasRole(\App\UserRole::Admin))
                    <div class="mb-2 mt-7 px-3 text-[11px] font-bold uppercase tracking-[0.2em] text-brand-300">Administração</div>
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Relatórios</x-nav-link>
                    @if ($isCompanyAdmin)
                        <x-nav-link :href="route('company-branches.index')" :active="request()->routeIs('company-branches.*')">Empresa e filiais</x-nav-link>
                    @endif
                    <x-nav-link :href="route('audit.index')" :active="request()->routeIs('audit.*')">Auditoria</x-nav-link>
                    <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Usuários</x-nav-link>
                    <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Produtos e estoque</x-nav-link>
                    <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">Configurações</x-nav-link>
                @endif
            </nav>

            <div class="rounded-2xl bg-white/8 p-4">
                <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                <div class="mt-1 text-xs text-brand-200">{{ auth()->user()->effectiveRole()->label() }}</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-warm-400 hover:text-warm-500">Sair do sistema</button>
                </form>
                <div class="mt-4 flex gap-3 text-[11px] text-brand-300">
                    <a href="{{ route('privacy') }}" class="hover:text-white">Privacidade</a>
                    <a href="{{ route('terms') }}" class="hover:text-white">Termos</a>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="border-b border-brand-100 bg-white px-5 py-4 sm:px-8 lg:px-10">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        @if ($establishmentSettings?->logo_path)
                            <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-brand-100 bg-white p-1.5 shadow-sm lg:hidden">
                                <img src="{{ route('branding.logo', ['v' => $establishmentSettings->updated_at?->timestamp]) }}" alt="Logo de {{ $establishmentSettings->name }}" class="size-full object-contain">
                            </div>
                        @endif
                        <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">{{ $currentCompany->name }} · {{ $currentBranch->name }}</p>
                        <h1 class="mt-1 text-xl font-bold text-slate-900">@yield('title', 'Painel')</h1>
                        </div>
                    </div>

                    <details class="relative lg:hidden">
                        <summary class="cursor-pointer list-none rounded-xl bg-brand-700 px-4 py-2 text-sm font-semibold text-white">Menu</summary>
                        <div class="absolute right-0 z-30 mt-2 w-64 rounded-2xl border border-brand-100 bg-white p-3 shadow-xl">
                            <nav class="flex flex-col gap-1 text-sm font-semibold text-slate-700">
                                <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('dashboard') }}">Visão geral</a>
                                <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('tabs.index') }}">Comandas</a>
                                @if (auth()->user()->hasRole(\App\UserRole::Admin, \App\UserRole::Cashier))
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('customers.index') }}">Clientes</a>
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('receivables.index') }}">Contas a receber</a>
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('monthly-closing.index') }}">Fechamento mensal</a>
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('cash.index') }}">Movimento do caixa</a>
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('cards.index') }}">Cartões</a>
                                @endif
                                @if (auth()->user()->hasRole(\App\UserRole::Admin))
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('reports.index') }}">Relatórios</a>
                                    @if ($isCompanyAdmin)
                                        <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('company-branches.index') }}">Empresa e filiais</a>
                                    @endif
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('audit.index') }}">Auditoria</a>
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('users.index') }}">Usuários</a>
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('products.index') }}">Produtos e estoque</a>
                                    <a class="rounded-lg px-3 py-2 hover:bg-brand-50" href="{{ route('settings.edit') }}">Configurações</a>
                                @endif
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
