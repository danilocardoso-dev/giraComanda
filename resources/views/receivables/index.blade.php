@extends('layouts.app')

@section('title', 'Contas a receber')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Clientes que ainda devem</h2>
            <p class="mt-1 text-sm text-slate-600">Veja quem deve, o que consumiu e quando precisa pagar.</p>
        </div>
        <a href="{{ route('monthly-closing.index') }}" class="inline-flex items-center justify-center rounded-xl border border-brand-200 bg-white px-5 py-3 text-sm font-bold text-brand-800 hover:bg-brand-50">Fechamento mensal</a>
    </div>

    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <article class="rounded-2xl bg-brand-900 p-5 text-white shadow-sm">
            <p class="text-sm font-semibold text-brand-200">Total que falta receber</p>
            <p class="mt-3 text-3xl font-black">R$ {{ number_format($openTotalCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs text-brand-200">{{ $debtorsCount }} {{ $debtorsCount === 1 ? 'cliente deve' : 'clientes devem' }} · {{ $openCount }} {{ $openCount === 1 ? 'anotação' : 'anotações' }}</p>
        </article>
        <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-red-700">Valor atrasado</p>
            <p class="mt-3 text-3xl font-black text-red-900">R$ {{ number_format($overdueTotalCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs font-semibold text-red-700">{{ $overdueCount }} {{ $overdueCount === 1 ? 'anotação passou' : 'anotações passaram' }} da data de pagamento</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-emerald-800">Ainda está no prazo</p>
            <p class="mt-3 text-3xl font-black text-emerald-950">R$ {{ number_format($notDueTotalCents / 100, 2, ',', '.') }}</p>
            <p class="mt-2 text-xs font-semibold text-emerald-700">{{ $notDueCount }} {{ $notDueCount === 1 ? 'anotação ainda não venceu' : 'anotações ainda não venceram' }}</p>
        </article>
    </section>

    <section class="mt-4 rounded-2xl border border-brand-200 bg-white px-5 py-4 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Como chegamos ao total</p>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm sm:text-base">
            <strong class="text-red-800">R$ {{ number_format($overdueTotalCents / 100, 2, ',', '.') }} atrasados</strong>
            <span class="text-slate-400">+</span>
            <strong class="text-emerald-800">R$ {{ number_format($notDueTotalCents / 100, 2, ',', '.') }} no prazo</strong>
            <span class="text-slate-400">=</span>
            <strong class="text-brand-950">R$ {{ number_format($openTotalCents / 100, 2, ',', '.') }} para receber</strong>
        </div>
        <p class="mt-2 text-sm text-slate-600">Logo abaixo você vê o nome do cliente, o item comprado e a data de cada anotação.</p>
    </section>

    <form method="GET" action="{{ route('receivables.index') }}" class="mt-6 flex gap-3 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
        <input name="q" value="{{ $search }}" placeholder="Buscar por nome ou CPF"
            class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Buscar</button>
    </form>

    <section class="mt-6" aria-labelledby="debtors-heading">
        <div>
            <h3 id="debtors-heading" class="text-xl font-black text-slate-950">Quem deve e o que comprou</h3>
            <p class="mt-1 text-sm text-slate-600">Cada anotação mostra o produto, o valor e se a data de pagamento já passou.</p>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            @forelse ($customers as $customer)
                <article class="overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
                    <header class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="text-lg font-black text-slate-950">{{ $customer->name }}</h4>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ $customer->masked_cpf }}</p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Este cliente deve</p>
                            <p class="mt-1 text-2xl font-black text-brand-900">R$ {{ number_format($customer->open_balance_cents / 100, 2, ',', '.') }}</p>
                        </div>
                    </header>

                    <div class="grid gap-3 p-4">
                        @foreach ($customer->receivables as $receivable)
                            @php($isOverdue = $receivable->due_date->isBefore(today()))
                            <section @class([
                                'rounded-xl border p-4',
                                'border-red-200 bg-red-50/60' => $isOverdue,
                                'border-emerald-200 bg-emerald-50/60' => ! $isOverdue,
                            ])>
                                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-brand-700">{{ $receivable->tab?->card?->code ?? 'Comanda' }} · anotado em {{ $receivable->created_at->format('d/m/Y') }}</p>
                                        <span @class([
                                            'mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-black',
                                            'bg-red-100 text-red-800' => $isOverdue,
                                            'bg-emerald-100 text-emerald-800' => ! $isOverdue,
                                        ])>
                                            {{ $isOverdue ? 'Venceu em '.$receivable->due_date->format('d/m/Y') : 'Vence em '.$receivable->due_date->format('d/m/Y') }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-semibold text-slate-500">Falta pagar</p>
                                        <p class="text-lg font-black text-slate-950">R$ {{ number_format(($receivable->amount_cents - $receivable->paid_cents) / 100, 2, ',', '.') }}</p>
                                    </div>
                                </div>
                                @include('receivables.partials.consumption-items', ['receivable' => $receivable, 'customer' => $customer])
                            </section>
                        @endforeach
                    </div>

                    <footer class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs text-slate-500">
                            @if ($customer->credit_limit_cents === null)
                                Sem limite de crédito configurado
                            @elseif ($customer->open_balance_cents > $customer->credit_limit_cents)
                                <strong class="text-red-700">Limite de crédito excedido</strong>
                            @else
                                Limite: R$ {{ number_format($customer->credit_limit_cents / 100, 2, ',', '.') }}
                            @endif
                        </div>
                        <a href="{{ route('receivables.show', $customer) }}" class="rounded-lg bg-brand-700 px-4 py-2 text-center text-sm font-bold text-white hover:bg-brand-800">Abrir extrato e receber</a>
                    </footer>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-brand-300 bg-white p-12 text-center text-slate-500 xl:col-span-2">Nenhum valor a receber.</div>
            @endforelse
        </div>
    </section>

    <div class="mt-6">{{ $customers->links() }}</div>
@endsection
