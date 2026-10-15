@extends('layouts.app')

@section('title', 'Comandas')

@section('content')
    <div class="space-y-6">
        <form method="POST" action="{{ route('tabs.scan') }}" data-qr-scan-form class="rounded-2xl bg-brand-900 p-4 text-white shadow-sm sm:p-5">
            @csrf
            <input type="hidden" name="qr_token" data-qr-token>

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-200">Leitura automática</p>
                    <h2 class="mt-1 text-xl font-bold sm:text-2xl">Aproxime o cartão</h2>
                </div>
                <span data-qr-camera-state class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-brand-100">
                    <span data-qr-camera-state-dot class="size-2 rounded-full bg-warm-300"></span>
                    <span data-qr-camera-state-label>Iniciando</span>
                </span>
            </div>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-brand-100">A leitura acontece sem apertar botões. Cartões em uso abrem a comanda existente.</p>

            <div class="mt-4 grid gap-4 md:grid-cols-[minmax(260px,380px)_minmax(0,1fr)] md:items-center">
                <div data-qr-scanner class="relative aspect-video overflow-hidden rounded-xl bg-slate-950">
                    <video data-qr-video autoplay muted playsinline class="size-full object-cover"></video>
                    <div class="pointer-events-none absolute inset-[12%] rounded-xl border-4 border-white/90 shadow-[0_0_0_999px_rgba(2,6,23,0.38)]"></div>
                    <div class="pointer-events-none absolute inset-x-1/4 top-1/2 h-0.5 bg-warm-400 shadow-[0_0_14px_rgba(251,191,36,0.9)]"></div>
                </div>

                <div class="min-w-0">
                    <p data-qr-status role="status" class="min-h-12 rounded-xl bg-brand-50 px-4 py-3 text-center text-sm font-semibold leading-6 text-brand-900">Preparando a câmera...</p>
                    <p data-qr-permission-help hidden class="mt-3 rounded-xl border border-red-300/30 bg-red-950/25 px-4 py-3 text-sm leading-6 text-red-100">
                        No navegador, clique no ícone ao lado do endereço, permita o uso da câmera e recarregue esta página.
                    </p>
                    <button type="button" data-qr-scanner-retry hidden class="mt-3 w-full rounded-xl bg-warm-500 px-5 py-3 text-sm font-black text-brand-950 hover:bg-warm-400 sm:w-auto">Ativar câmera</button>
                    <x-input-error :messages="$errors->get('qr_token')" class="text-red-200" />
                </div>
            </div>
        </form>

        <section class="min-w-0">
                <div>
                    <h2 class="text-2xl font-bold text-slate-950">Comandas por cartão</h2>
                    <p class="mt-1 text-sm text-slate-600">Acompanhe consumo, peso e rateios antes do fechamento.</p>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ([\App\TabStatus::Open->value => 'Abertas', \App\TabStatus::Closed->value => 'Fechadas', \App\TabStatus::Cancelled->value => 'Canceladas', 'all' => 'Todas'] as $filter => $label)
                        <a href="{{ route('tabs.index', ['status' => $filter]) }}" @class([
                            'rounded-xl px-4 py-2 text-sm font-bold',
                            'bg-brand-700 text-white' => $status === $filter,
                            'bg-white text-brand-800 shadow-sm hover:bg-brand-50' => $status !== $filter,
                        ])>{{ $label }}</a>
                    @endforeach
                </div>

                <div class="mt-5 grid items-start gap-4 md:grid-cols-2">
                    @forelse ($tabs as $tab)
                        <a href="{{ route('tabs.show', $tab) }}" class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm hover:border-brand-300">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Cartão</p>
                                    <h3 class="mt-2 text-2xl font-black text-slate-950">{{ $tab->card->code }}</h3>
                                </div>
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-amber-100 text-amber-800' => $tab->status === \App\TabStatus::Open,
                                    'bg-slate-200 text-slate-700' => $tab->status === \App\TabStatus::Closed,
                                    'bg-red-100 text-red-800' => $tab->status === \App\TabStatus::Cancelled,
                                ])>{{ $tab->status->label() }}</span>
                            </div>
                            <div class="mt-5 flex items-end justify-between gap-4 border-t border-slate-100 pt-4">
                                <div class="min-w-0 text-sm text-slate-500">
                                    <p>{{ $tab->items_count }} {{ $tab->items_count === 1 ? 'item' : 'itens' }}</p>
                                    <p class="mt-1 truncate">{{ $tab->customer?->name ?? 'Sem cliente definido' }}</p>
                                </div>
                                <p class="shrink-0 text-xl font-black text-brand-900">R$ {{ number_format($tab->total_cents / 100, 2, ',', '.') }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-brand-300 bg-brand-50 p-8 text-center text-slate-600">Nenhuma comanda encontrada.</div>
                    @endforelse

                    <form method="POST" action="{{ route('tabs.store') }}" class="h-fit rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
                        @csrf
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Nova operação</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-950">Abrir comanda</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Selecione o cartão entregue ao cliente.</p>

                        <label for="card_id" class="mt-4 block text-sm font-semibold text-slate-700">Cartão disponível</label>
                        <select id="card_id" name="card_id" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                            <option value="">Selecione</option>
                            @foreach ($availableCards as $card)
                                <option value="{{ $card->id }}" @selected((string) old('card_id') === (string) $card->id)>{{ $card->code }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('card_id')" />

                        <button type="submit" @disabled($availableCards->isEmpty()) class="mt-4 w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800 disabled:cursor-not-allowed disabled:bg-slate-300">Abrir comanda</button>

                        @if ($availableCards->isEmpty())
                            <p class="mt-3 text-xs text-slate-500">Não há cartões disponíveis. Gere ou libere um cartão primeiro.</p>
                        @endif
                    </form>
                </div>

                <div class="mt-6">{{ $tabs->links() }}</div>
        </section>
    </div>
@endsection
