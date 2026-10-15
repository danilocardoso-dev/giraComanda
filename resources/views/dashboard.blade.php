@extends('layouts.app')

@section('title', 'Visão geral')

@section('content')
    @php
        $canManageOperation = auth()->user()->hasRole(\App\UserRole::Admin, \App\UserRole::Cashier);
        $isAdmin = auth()->user()->hasRole(\App\UserRole::Admin);
    @endphp

    <section class="relative overflow-hidden rounded-3xl bg-brand-900 p-6 text-white shadow-sm sm:p-9">
        <div class="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full border-[42px] border-white/5"></div>
        <div class="pointer-events-none absolute -bottom-32 right-56 size-64 rounded-full bg-warm-500/10 blur-3xl"></div>

        <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-center">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-warm-400">Central de operação</p>
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold text-emerald-200">
                        <span class="size-2 rounded-full bg-emerald-400"></span>
                        {{ $currentBranch->name }} ativa
                    </span>
                </div>

                <h2 class="mt-4 max-w-3xl text-3xl font-black tracking-[-0.035em] sm:text-4xl lg:text-5xl">
                    Atendimento rápido, controle simples.
                </h2>
                <p class="mt-4 max-w-2xl text-base leading-7 text-brand-100 sm:text-lg">
                    Acompanhe a operação da <strong class="text-white">{{ $currentCompany->name }}</strong> e acesse o que precisa sem perder tempo.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('tabs.index', ['status' => \App\TabStatus::Open->value]) }}" class="inline-flex items-center gap-2 rounded-xl bg-warm-500 px-5 py-3 text-sm font-black text-brand-950 transition hover:bg-warm-400 focus:outline-none focus:ring-3 focus:ring-warm-300/50">
                        Ir para comandas
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </a>
                    @if ($canManageOperation)
                        <a href="{{ route('cash.index') }}" class="inline-flex items-center rounded-xl border border-white/20 bg-white/10 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/15 focus:outline-none focus:ring-3 focus:ring-white/20">
                            Ver movimento do caixa
                        </a>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/8 p-6 backdrop-blur-sm">
                @if ($establishmentSettings?->logo_path)
                    <div class="grid min-h-40 place-items-center overflow-hidden rounded-2xl bg-white p-4 shadow-inner">
                        <img src="{{ route('branding.logo', ['v' => $establishmentSettings->updated_at?->timestamp]) }}" alt="Logo de {{ $establishmentSettings->name }}" class="max-h-32 max-w-full object-contain drop-shadow-sm">
                    </div>
                    <p class="mt-4 text-lg font-black text-white">{{ $establishmentSettings->name }}</p>
                    <div class="mt-3 flex items-center gap-2 text-xs text-brand-200"><span>Tecnologia</span><x-brand-logo size="sm" inverted /></div>
                @else
                    <x-brand-logo size="lg" inverted />
                    <p class="mt-5 text-sm leading-6 text-brand-100">Comandas, clientes, caixa e estoque girando em um só lugar.</p>
                @endif
                <div class="mt-5 flex items-center justify-between border-t border-white/10 pt-4 text-xs">
                    <span class="text-brand-200">Filial em uso</span>
                    <strong class="text-white">{{ $currentBranch->name }}</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-7">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-black text-slate-950">Resumo de agora</h2>
                <p class="mt-1 text-sm text-slate-600">Selecione um indicador para abrir a listagem correspondente.</p>
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Dados da filial {{ $currentBranch->name }}</p>
        </div>

        <div class="mt-4 grid grid-cols-[repeat(auto-fit,minmax(210px,1fr))] gap-4">
            <a href="{{ route('tabs.index', ['status' => \App\TabStatus::Open->value]) }}" class="group rounded-2xl border border-warm-200 bg-warm-50 p-5 shadow-sm transition hover:-translate-y-1 hover:border-warm-400 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-warm-400/30">
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-11 place-items-center rounded-xl bg-warm-500 text-brand-950">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v16l-3-2-4 2-4-2-3 2V5a2 2 0 0 1 2-2Z" stroke-linejoin="round" /><path d="M9 8h6M9 12h4" stroke-linecap="round" /></svg>
                    </span>
                    <svg class="size-5 text-warm-700 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </div>
                <p class="mt-5 text-4xl font-black tracking-tight text-brand-950">{{ $openTabs }}</p>
                <p class="mt-1 font-bold text-slate-800">Comandas abertas</p>
                <p class="mt-2 text-xs text-slate-500">Acompanhar atendimento</p>
            </a>

            @if ($canManageOperation)
                <a href="{{ route('customers.index', ['status' => 'active']) }}" class="group rounded-2xl border border-brand-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-brand-300 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-brand-300/40">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-11 place-items-center rounded-xl bg-brand-100 text-brand-800">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <svg class="size-5 text-brand-500 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                    <p class="mt-5 text-4xl font-black tracking-tight text-slate-950">{{ $activeCustomers }}</p>
                    <p class="mt-1 font-bold text-slate-800">Clientes ativos</p>
                    <p class="mt-2 text-xs text-slate-500">Consultar cadastros</p>
                </a>

                <a href="{{ route('cards.index', ['status' => \App\CardStatus::Available->value]) }}" class="group rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5 shadow-sm transition hover:-translate-y-1 hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-emerald-300/40">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-11 place-items-center rounded-xl bg-emerald-100 text-emerald-800">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="3" /><path d="M2 10h20M7 15h3" stroke-linecap="round" /></svg>
                        </span>
                        <svg class="size-5 text-emerald-600 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                    <p class="mt-5 text-4xl font-black tracking-tight text-emerald-900">{{ $availableCards }}</p>
                    <p class="mt-1 font-bold text-slate-800">Cartões disponíveis</p>
                    <p class="mt-2 text-xs text-slate-500">Prontos para nova comanda</p>
                </a>

                <a href="{{ route('cards.index', ['status' => \App\CardStatus::Blocked->value]) }}" class="group rounded-2xl border border-red-100 bg-red-50/60 p-5 shadow-sm transition hover:-translate-y-1 hover:border-red-300 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-red-300/40">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-11 place-items-center rounded-xl bg-red-100 text-red-700">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" stroke-linejoin="round" /><path d="m9 9 6 6m0-6-6 6" stroke-linecap="round" /></svg>
                        </span>
                        <svg class="size-5 text-red-500 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                    <p class="mt-5 text-4xl font-black tracking-tight text-red-800">{{ $blockedCards }}</p>
                    <p class="mt-1 font-bold text-slate-800">Cartões bloqueados</p>
                    <p class="mt-2 text-xs text-slate-500">Revisar ou reativar</p>
                </a>
            @endif

            @if ($isAdmin)
                <a href="{{ route('users.index') }}" class="group rounded-2xl border border-violet-100 bg-violet-50/60 p-5 shadow-sm transition hover:-translate-y-1 hover:border-violet-300 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-violet-300/40">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-11 place-items-center rounded-xl bg-violet-100 text-violet-700">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <svg class="size-5 text-violet-500 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                    <p class="mt-5 text-4xl font-black tracking-tight text-violet-900">{{ $activeUsers }}</p>
                    <p class="mt-1 font-bold text-slate-800">Usuários ativos</p>
                    <p class="mt-2 text-xs text-slate-500">Gerenciar equipe e acessos</p>
                </a>
            @endif
        </div>
    </section>

    @if ($canManageOperation)
        <section class="mt-7">
            <h2 class="text-xl font-black text-slate-950">Atalhos de operação</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <a href="{{ route('customers.create') }}" class="group rounded-2xl border border-brand-100 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                    <span class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Cadastro</span>
                    <div class="mt-2 flex items-end justify-between gap-4">
                        <div><p class="font-black text-slate-950">Novo cliente</p><p class="mt-1 text-sm text-slate-600">CPF, vencimento e limite opcional.</p></div>
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-700 text-xl font-black text-white transition group-hover:bg-brand-800">+</span>
                    </div>
                </a>
                <a href="{{ route('cards.index') }}" class="group rounded-2xl border border-brand-100 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                    <span class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Identificação</span>
                    <div class="mt-2 flex items-end justify-between gap-4">
                        <div><p class="font-black text-slate-950">Gerenciar cartões</p><p class="mt-1 text-sm text-slate-600">Gerar, imprimir e alterar situação.</p></div>
                        <svg class="size-6 shrink-0 text-brand-600 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                </a>
                <a href="{{ route('receivables.index') }}" class="group rounded-2xl border border-brand-100 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                    <span class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Financeiro</span>
                    <div class="mt-2 flex items-end justify-between gap-4">
                        <div><p class="font-black text-slate-950">Contas a receber</p><p class="mt-1 text-sm text-slate-600">Consultar anotações e pagamentos.</p></div>
                        <svg class="size-6 shrink-0 text-brand-600 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                </a>
            </div>
        </section>
    @endif

    @if ($isAdmin)
        <section class="mt-7 grid gap-4 lg:grid-cols-2">
            <a href="{{ route('reports.index') }}" class="block rounded-2xl border border-warm-300 bg-warm-50 p-6 transition hover:bg-warm-100">
                <p class="font-black text-slate-950">Relatórios gerenciais</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">Vendas, recebimentos, formas de pagamento, produtos mais vendidos e estoque.</p>
            </a>
            <a href="{{ route('audit.index') }}" class="block rounded-2xl border border-brand-200 bg-white p-6 transition hover:bg-brand-50">
                <p class="font-black text-slate-950">Trilha de auditoria</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">Confira quem realizou alterações importantes e quando cada ação aconteceu.</p>
            </a>
        </section>
    @endif
@endsection
