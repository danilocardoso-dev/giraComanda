@extends('layouts.platform')

@section('title', 'Clientes e assinaturas')

@section('content')
    <div>
        <h2 class="text-2xl font-black text-slate-950">Empresas clientes</h2>
        <p class="mt-1 text-sm text-slate-600">Controle acesso, assinatura, cobranças e suporte de cada cliente.</p>
    </div>

    <section id="novo-estabelecimento" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-bold text-slate-950">Novo estabelecimento</h3>
        <p class="mt-1 text-sm text-slate-500">A primeira filial e o usuário proprietário serão criados juntos.</p>
        <form method="POST" action="{{ route('platform.companies.store') }}" class="mt-5 grid gap-4 lg:grid-cols-2">
            @csrf
            <div><label for="company_name" class="text-sm font-bold text-slate-700">Nome do estabelecimento</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-slate-600 focus:ring-slate-600"><x-input-error :messages="$errors->get('company_name')" /></div>
            <div><label for="branch_name" class="text-sm font-bold text-slate-700">Primeira filial</label><input id="branch_name" name="branch_name" value="{{ old('branch_name', 'Matriz') }}" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-slate-600 focus:ring-slate-600"><x-input-error :messages="$errors->get('branch_name')" /></div>
            <div><label for="owner_name" class="text-sm font-bold text-slate-700">Nome do proprietário</label><input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-slate-600 focus:ring-slate-600"><x-input-error :messages="$errors->get('owner_name')" /></div>
            <div><label for="owner_email" class="text-sm font-bold text-slate-700">E-mail do proprietário</label><input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-slate-600 focus:ring-slate-600"><x-input-error :messages="$errors->get('owner_email')" /></div>
            <div><label for="owner_password" class="text-sm font-bold text-slate-700">Senha inicial</label><input id="owner_password" name="owner_password" type="password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-slate-600 focus:ring-slate-600"><x-input-error :messages="$errors->get('owner_password')" /></div>
            <div><label for="owner_password_confirmation" class="text-sm font-bold text-slate-700">Confirmar senha</label><input id="owner_password_confirmation" name="owner_password_confirmation" type="password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-slate-600 focus:ring-slate-600"></div>
            <div class="lg:col-span-2"><button class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800">Criar estabelecimento</button></div>
        </form>
    </section>

    <section class="mt-6 space-y-4">
        @forelse ($companies as $company)
            @php
                $subscriptionLabel = $company->subscription?->statusLabel() ?? 'Sem assinatura';
                $owner = $company->memberships->firstWhere('role', \App\CompanyRole::CompanyAdmin)?->user;
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-black text-slate-950">{{ $company->name }}</h3>
                            <span @class(['rounded-full px-2.5 py-1 text-xs font-bold', 'bg-emerald-100 text-emerald-800' => $subscriptionLabel === 'Em dia', 'bg-amber-100 text-amber-800' => $subscriptionLabel === 'Em teste', 'bg-red-100 text-red-800' => in_array($subscriptionLabel, ['Inadimplente', 'Suspenso'], true), 'bg-slate-200 text-slate-700' => in_array($subscriptionLabel, ['Cancelado', 'Sem assinatura'], true)])>{{ $subscriptionLabel }}</span>
                            @unless ($company->active)<span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-800">Acesso desativado</span>@endunless
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $owner?->name ?? 'Sem proprietário' }} @if ($owner)· {{ $owner->email }}@endif</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $company->branches_count }} {{ $company->branches_count === 1 ? 'filial' : 'filiais' }} · {{ $company->memberships_count }} {{ $company->memberships_count === 1 ? 'usuário' : 'usuários' }} · <strong class="text-slate-800">R$ {{ number_format(($company->subscription?->monthly_amount_cents ?? 0) / 100, 2, ',', '.') }}/mês</strong></p>
                        <div class="mt-3 flex flex-wrap gap-2">@foreach ($company->branches as $branch)<span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $branch->name }}{{ $branch->active ? '' : ' · inativa' }}</span>@endforeach</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('platform.companies.show', $company) }}" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Abrir painel</a>
                        <form method="POST" action="{{ route('platform.companies.update', $company) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="active" value="{{ $company->active ? '0' : '1' }}">
                            <button class="rounded-xl px-4 py-2.5 text-sm font-bold {{ $company->active ? 'border border-red-200 text-red-700 hover:bg-red-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">{{ $company->active ? 'Desativar acesso' : 'Ativar acesso' }}</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">Nenhum estabelecimento cadastrado.</div>
        @endforelse
    </section>

    <div class="mt-6">{{ $companies->links() }}</div>
@endsection
