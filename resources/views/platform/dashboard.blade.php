@extends('layouts.platform')

@section('title', 'Visão geral da plataforma')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-600">Gestão do negócio</p>
            <h2 class="mt-2 text-3xl font-black text-slate-950">Sua plataforma em números</h2>
            <p class="mt-2 text-sm text-slate-600">Acompanhe clientes, mensalidades e pendências em um só lugar.</p>
        </div>
        <a href="{{ route('platform.companies.index') }}#novo-estabelecimento" class="rounded-xl bg-slate-950 px-5 py-3 text-center text-sm font-bold text-white hover:bg-slate-800">Cadastrar cliente</a>
    </div>

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl bg-slate-950 p-6 text-white shadow-sm">
            <p class="text-sm font-semibold text-slate-300">Receita mensal prevista</p>
            <p class="mt-3 text-3xl font-black">R$ {{ number_format($monthlyRecurringRevenueCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs text-slate-400">Soma das assinaturas ativas e em teste</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
            <p class="text-sm font-semibold text-emerald-800">Recebido neste mês</p>
            <p class="mt-3 text-3xl font-black text-emerald-950">R$ {{ number_format($receivedThisMonthCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs text-emerald-700">Mensalidades confirmadas</p>
        </article>
        <article class="rounded-2xl border border-red-200 bg-red-50 p-6 shadow-sm">
            <p class="text-sm font-semibold text-red-800">Em atraso</p>
            <p class="mt-3 text-3xl font-black text-red-950">R$ {{ number_format($overdueAmountCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs text-red-700">{{ $overdueCompaniesCount }} {{ $overdueCompaniesCount === 1 ? 'cliente inadimplente' : 'clientes inadimplentes' }}</p>
        </article>
        <article class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
            <p class="text-sm font-semibold text-amber-800">A vencer neste mês</p>
            <p class="mt-3 text-3xl font-black text-amber-950">R$ {{ number_format($dueThisMonthCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs text-amber-700">Mensalidades ainda não pagas</p>
        </article>
    </section>

    <section class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['label' => 'Clientes', 'value' => $companiesCount, 'detail' => $activeCompaniesCount.' ativos'],
            ['label' => 'Em teste', 'value' => $trialCompaniesCount, 'detail' => 'período de avaliação'],
            ['label' => 'Filiais', 'value' => $branchesCount, 'detail' => 'em toda a base'],
            ['label' => 'Usuários', 'value' => $establishmentUsersCount, 'detail' => 'acessos ativos'],
            ['label' => 'Comandas abertas', 'value' => $openTabsCount, 'detail' => 'operação em andamento'],
        ] as $indicator)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">{{ $indicator['label'] }}</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ $indicator['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $indicator['detail'] }}</p>
            </article>
        @endforeach
        <a href="{{ route('platform.backups.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-slate-400">
            <p class="text-sm font-semibold text-slate-500">Backups</p>
            <p class="mt-3 text-sm font-bold text-slate-950">Gerenciar cópias →</p>
        </a>
    </section>

    <div class="mt-7 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h3 class="font-bold text-slate-950">Clientes recentes</h3><p class="mt-1 text-sm text-slate-500">Acesse rapidamente cada conta.</p></div>
                <a href="{{ route('platform.companies.index') }}" class="text-sm font-bold text-slate-700 hover:text-slate-950">Ver todos</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($recentCompanies as $company)
                    <a href="{{ route('platform.companies.show', $company) }}" class="flex flex-col gap-3 px-5 py-4 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="font-bold text-slate-950">{{ $company->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $company->branches_count }} filiais · {{ $company->memberships_count }} usuários</p></div>
                        <div class="text-left sm:text-right"><p class="font-bold text-slate-950">R$ {{ number_format(($company->subscription?->monthly_amount_cents ?? 0) / 100, 2, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">por mês</p></div>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-slate-500">Nenhum cliente cadastrado.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-950">Cobranças vencidas</h3><p class="mt-1 text-sm text-slate-500">Quem precisa da sua atenção agora.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse ($overdueInvoices as $invoice)
                    <div class="flex flex-col gap-3 px-5 py-4 hover:bg-red-50 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ route('platform.companies.show', $invoice->subscription->company) }}" class="min-w-0 flex-1"><p class="font-bold text-slate-950">{{ $invoice->subscription->company->name }}</p><p class="mt-1 text-xs text-red-700">Venceu em {{ $invoice->due_date->format('d/m/Y') }}</p></a>
                        <div class="flex items-center justify-between gap-3 sm:justify-end">
                            <p class="font-black text-red-800">R$ {{ number_format($invoice->amount_cents / 100, 2, ',', '.') }}</p>
                            @if ($whatsappUrls->get($invoice->id))
                                <a href="{{ $whatsappUrls->get($invoice->id) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Cobrar</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-slate-500">Nenhuma mensalidade vencida.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
