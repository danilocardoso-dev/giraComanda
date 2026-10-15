@extends('layouts.app')

@section('title', 'Cartões')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Cartões reutilizáveis</h2>
            <p class="mt-1 text-sm text-slate-600">Controle os cartões que identificarão as comandas.</p>
        </div>
        <form method="POST" action="{{ route('cards.store') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">
                Gerar novo cartão
            </button>
        </form>
    </div>

    <div class="mt-6 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap gap-2">
            @foreach (['active' => 'Ativos', \App\CardStatus::Available->value => 'Disponíveis', \App\CardStatus::InUse->value => 'Em uso', \App\CardStatus::Blocked->value => 'Inativos', 'all' => 'Todos'] as $filter => $label)
                <a href="{{ route('cards.index', ['status' => $filter, 'q' => $search ?: null]) }}" @class([
                    'rounded-xl px-4 py-2 text-sm font-bold',
                    'bg-brand-700 text-white' => $status === $filter,
                    'bg-brand-50 text-brand-800 hover:bg-brand-100' => $status !== $filter,
                ])>{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('cards.index') }}" class="mt-4 flex gap-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <input name="q" value="{{ $search }}" placeholder="Buscar por CT-001"
                class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Buscar</button>
        </form>
    </div>

    @if ($cards->isNotEmpty())
        <form id="bulk-card-print-form" method="GET" action="{{ route('cards.print-bulk') }}" target="_blank" rel="noopener"
            class="mt-6 flex flex-col gap-4 rounded-2xl border border-brand-200 bg-brand-900 p-4 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
            data-bulk-card-print-form>
            <div class="flex items-center gap-3">
                <input id="select-all-cards" type="checkbox" data-card-select-all
                    class="size-5 rounded border-white/30 bg-white/10 text-warm-500 focus:ring-warm-400">
                <label for="select-all-cards" class="cursor-pointer">
                    <span class="block text-sm font-black">Selecionar todos desta página</span>
                    <span class="text-xs text-brand-200" data-card-selection-count>Nenhum cartão selecionado</span>
                </label>
            </div>
            <button type="submit" data-bulk-card-print-button
                class="rounded-xl bg-warm-500 px-5 py-3 text-sm font-black text-brand-950 hover:bg-warm-400 disabled:cursor-not-allowed disabled:bg-white/15 disabled:text-brand-300">
                Imprimir selecionados
            </button>
        </form>
        <x-input-error :messages="$errors->get('cards')" />
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($cards as $card)
            <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/60 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20">
                <div class="flex items-center justify-between gap-4">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-bold text-slate-700">
                        <input type="checkbox" name="cards[]" value="{{ $card->getKey() }}" form="bulk-card-print-form" data-card-select
                            class="size-5 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                        Selecionar
                    </label>
                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-bold',
                        'bg-emerald-100 text-emerald-800' => $card->status === \App\CardStatus::Available,
                        'bg-amber-100 text-amber-800' => $card->status === \App\CardStatus::InUse,
                        'bg-red-100 text-red-800' => $card->status === \App\CardStatus::Blocked,
                    ])>{{ $card->status->label() }}</span>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Cartão</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-950">{{ $card->code }}</h3>
                </div>
                <div class="mt-5 grid gap-2 border-t border-slate-100 pt-4">
                    <a href="{{ route('cards.print', $card) }}" target="_blank" rel="noopener"
                        class="w-full rounded-xl bg-brand-700 px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-brand-800">
                        Imprimir somente este
                    </a>
                    @if ($card->status === \App\CardStatus::InUse)
                        <p class="rounded-xl bg-amber-50 px-4 py-2.5 text-center text-sm font-bold text-amber-800">Feche a comanda para liberar o cartão</p>
                    @else
                        <form method="POST" action="{{ route('cards.update', $card) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="{{ $card->status === \App\CardStatus::Available ? \App\CardStatus::Blocked->value : \App\CardStatus::Available->value }}">
                            <button type="submit" @class([
                                'w-full rounded-xl px-4 py-2.5 text-sm font-bold',
                                'bg-red-50 text-red-700 hover:bg-red-100' => $card->status === \App\CardStatus::Available,
                                'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' => $card->status === \App\CardStatus::Blocked,
                            ])>
                                {{ $card->status === \App\CardStatus::Available ? 'Desativar cartão' : 'Ativar cartão' }}
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="sm:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-brand-300 bg-brand-50 p-12 text-center text-slate-600">
                Nenhum cartão cadastrado.
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $cards->links() }}</div>
@endsection
