@extends('layouts.app')

@section('title', 'Empresa e filiais')

@section('content')
    <section class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div class="rounded-3xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">{{ $company->name }}</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-950">Filiais cadastradas</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Cada filial possui comandas, cartões, caixa e movimentações independentes.</p>
                </div>
                <span class="rounded-full bg-brand-100 px-3 py-1 text-sm font-bold text-brand-800">{{ $branches->count() }} {{ $branches->count() === 1 ? 'filial' : 'filiais' }}</span>
            </div>

            <div class="mt-7 space-y-4">
                @foreach ($branches as $branch)
                    <form method="POST" action="{{ route('company-branches.update', $branch) }}" class="rounded-2xl border border-slate-200 p-5">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">Nome da filial</span>
                                <input name="name" value="{{ $branch->name }}" maxlength="120" required class="mt-2 w-full rounded-xl border-slate-300 focus:border-brand-500 focus:ring-brand-500">
                            </label>
                            <div class="flex flex-wrap items-center gap-4 pb-1">
                                <input type="hidden" name="active" value="0">
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <input type="checkbox" name="active" value="1" @checked($branch->active) class="rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                                    Ativa
                                </label>
                                @if ($branch->id === $currentBranch->id)
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Em uso</span>
                                @endif
                                <button type="submit" class="rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800">Salvar</button>
                            </div>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            <form method="POST" action="{{ route('company-branches.store') }}" class="rounded-3xl border border-brand-100 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-xl font-bold text-slate-950">Nova filial</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">A filial será criada ativa e estará disponível no seletor.</p>
                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-slate-700">Nome</span>
                    <input name="name" value="{{ old('name') }}" maxlength="120" required placeholder="Ex.: Unidade Centro" class="mt-2 w-full rounded-xl border-slate-300 focus:border-brand-500 focus:ring-brand-500">
                </label>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                <button type="submit" class="mt-5 w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Cadastrar filial</button>
            </form>

            <form method="POST" action="{{ route('company-customer-sharing.update') }}" class="rounded-3xl border border-warm-200 bg-warm-50 p-6 shadow-sm">
                @csrf
                @method('PUT')
                <h2 class="text-xl font-bold text-slate-950">Compartilhamento de clientes</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Quando ativo, todas as filiais da empresa podem localizar os mesmos clientes.</p>
                <input type="hidden" name="customer_sharing_enabled" value="0">
                <label class="mt-5 flex items-start gap-3 rounded-2xl bg-white p-4">
                    <input type="checkbox" name="customer_sharing_enabled" value="1" @checked($company->customer_sharing_enabled) class="mt-1 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                    <span>
                        <span class="block font-bold text-slate-900">Compartilhar clientes entre filiais</span>
                        <span class="mt-1 block text-sm leading-5 text-slate-600">Desative para cada filial visualizar somente os clientes cadastrados nela.</span>
                    </span>
                </label>
                <button type="submit" class="mt-5 w-full rounded-xl bg-warm-500 px-5 py-3 text-sm font-bold text-brand-950 hover:bg-warm-400">Salvar configuração</button>
            </form>
        </div>
    </section>
@endsection
