<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') · {{ config('app.name') }}</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" data-menu-orders-pending-url="{{ route('menu-orders.pending') }}">
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
    @php
        $navUser = auth()->user();
        $isNavAdmin = $navUser->hasRole(\App\UserRole::Admin);
        $hasFinanceAccess = $navUser->hasRole(\App\UserRole::Admin, \App\UserRole::Cashier);
        $navigation = [
            ['label' => 'Visão geral', 'route' => 'dashboard', 'active' => 'dashboard'],
            ['label' => 'Atendimento', 'items' => [
                ['label' => 'Comandas', 'route' => 'tabs.index', 'active' => 'tabs.*'],
                ['label' => 'Pedidos do cardápio', 'route' => 'menu-orders.index', 'active' => 'menu-orders.*', 'badge' => true],
                ['label' => 'Cartões', 'route' => 'cards.index', 'active' => 'cards.*', 'visible' => $hasFinanceAccess],
            ]],
            ['label' => 'Clientes e financeiro', 'items' => [
                ['label' => 'Clientes', 'route' => 'customers.index', 'active' => 'customers.*'],
                ['label' => 'Contas a receber', 'route' => 'receivables.index', 'active' => 'receivables.*'],
                ['label' => 'Fechamento mensal', 'route' => 'monthly-closing.index', 'active' => 'monthly-closing.*'],
                ['label' => 'Movimento do caixa', 'route' => 'cash.index', 'active' => 'cash.*'],
            ], 'visible' => $hasFinanceAccess],
            ['label' => 'Catálogo', 'items' => [
                ['label' => 'Produtos e estoque', 'route' => 'products.index', 'active' => 'products.*'],
                ['label' => 'Cardápio digital', 'route' => 'menu-settings.edit', 'active' => 'menu-settings.*'],
            ], 'visible' => $isNavAdmin],
            ['label' => 'Gestão', 'items' => [
                ['label' => 'Relatórios', 'route' => 'reports.index', 'active' => 'reports.*'],
                ['label' => 'Usuários', 'route' => 'users.index', 'active' => 'users.*'],
                ['label' => 'Auditoria', 'route' => 'audit.index', 'active' => 'audit.*'],
            ], 'visible' => $isNavAdmin],
            ['label' => 'Empresa', 'items' => [
                ['label' => 'Empresa e filiais', 'route' => 'company-branches.index', 'active' => 'company-branches.*', 'visible' => $isCompanyAdmin],
                ['label' => 'Assinatura', 'route' => 'subscription.show', 'active' => 'subscription.*', 'visible' => $isCompanyAdmin],
                ['label' => 'Configurações', 'route' => 'settings.edit', 'active' => 'settings.*'],
            ], 'visible' => $isNavAdmin],
        ];
        $navigation = collect($navigation)
            ->filter(fn (array $entry): bool => $entry['visible'] ?? true)
            ->map(function (array $entry): array {
                if (isset($entry['items'])) {
                    $entry['items'] = collect($entry['items'])->filter(fn (array $item): bool => $item['visible'] ?? true)->values()->all();
                    $entry['isActive'] = collect($entry['items'])->contains(fn (array $item): bool => request()->routeIs($item['active']));
                    $entry['hasBadge'] = collect($entry['items'])->contains(fn (array $item): bool => $item['badge'] ?? false);
                } else {
                    $entry['isActive'] = request()->routeIs($entry['active']);
                }

                return $entry;
            })
            ->filter(fn (array $entry): bool => ! isset($entry['items']) || $entry['items'] !== [])
            ->values();
    @endphp
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
        <aside class="hidden bg-brand-900 px-5 py-6 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:self-start">
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

            <nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Menu principal">
                @foreach ($navigation as $entry)
                    @if (isset($entry['items']))
                        <details class="group" @if ($entry['isActive']) open @endif>
                            <summary @class([
                                'flex cursor-pointer list-none items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-white/8 hover:text-white [&::-webkit-details-marker]:hidden',
                                'text-white' => $entry['isActive'],
                                'text-brand-100' => ! $entry['isActive'],
                            ])>
                                <span>{{ $entry['label'] }}</span>
                                <span class="flex items-center gap-2">
                                    @if ($entry['hasBadge'])
                                        <span data-menu-orders-badge hidden class="rounded-full bg-warm-500 px-2 py-0.5 text-xs font-black text-brand-950 group-open:hidden">0</span>
                                    @endif
                                    <svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                                </span>
                            </summary>
                            <div class="ml-4 mt-1 flex flex-col gap-1 border-l border-white/10 pl-3">
                                @foreach ($entry['items'] as $item)
                                    <x-nav-link :href="route($item['route'])" :active="request()->routeIs($item['active'])">
                                        @if ($item['badge'] ?? false)
                                            <span class="flex w-full items-center justify-between gap-2">{{ $item['label'] }} <span data-menu-orders-badge hidden class="rounded-full bg-warm-500 px-2 py-0.5 text-xs font-black text-brand-950">0</span></span>
                                        @else
                                            {{ $item['label'] }}
                                        @endif
                                    </x-nav-link>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <x-nav-link :href="route($entry['route'])" :active="$entry['isActive']">{{ $entry['label'] }}</x-nav-link>
                    @endif
                @endforeach
            </nav>

            <div class="mt-4 rounded-2xl bg-white/8 p-4">
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
                            <nav class="flex max-h-[70vh] flex-col gap-1 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden text-sm font-semibold text-slate-700">
                                @foreach ($navigation as $entry)
                                    @if (isset($entry['items']))
                                        <details class="group" @if ($entry['isActive']) open @endif>
                                            <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2 hover:bg-brand-50 [&::-webkit-details-marker]:hidden {{ $entry['isActive'] ? 'text-brand-800' : '' }}">
                                                <span>{{ $entry['label'] }}</span>
                                                <span class="flex items-center gap-2">
                                                    @if ($entry['hasBadge'])
                                                        <span data-menu-orders-badge hidden class="rounded-full bg-warm-500 px-2 py-0.5 text-xs font-black text-brand-950 group-open:hidden">0</span>
                                                    @endif
                                                    <svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                                                </span>
                                            </summary>
                                            <div class="ml-3 flex flex-col gap-1 border-l border-brand-100 pl-2">
                                                @foreach ($entry['items'] as $item)
                                                    <a class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-brand-50 {{ request()->routeIs($item['active']) ? 'bg-brand-50 text-brand-800' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}@if ($item['badge'] ?? false) <span data-menu-orders-badge hidden class="rounded-full bg-warm-500 px-2 py-0.5 text-xs font-black text-brand-950">0</span>@endif</a>
                                                @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <a class="rounded-lg px-3 py-2 hover:bg-brand-50 {{ $entry['isActive'] ? 'bg-brand-50 text-brand-800' : '' }}" href="{{ route($entry['route']) }}">{{ $entry['label'] }}</a>
                                    @endif
                                @endforeach
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
