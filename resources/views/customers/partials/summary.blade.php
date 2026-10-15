<div class="flex flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Resumo do cliente</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $customer->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $customer->formatted_cpf }}{{ $customer->formatted_phone ? ' · '.$customer->formatted_phone : '' }}</p>
        </div>
        <button type="button" data-customer-summary-close aria-label="Fechar resumo" class="grid size-10 shrink-0 place-items-center rounded-full bg-slate-100 text-xl font-bold text-slate-600 hover:bg-slate-200">×</button>
    </div>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-brand-100 bg-brand-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-600">Total anotado</p>
            <p class="mt-2 text-2xl font-black text-brand-950">R$ {{ number_format($total_annotated_cents / 100, 2, ',', '.') }}</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Total pago</p>
            <p class="mt-2 text-2xl font-black text-emerald-900">R$ {{ number_format($total_paid_cents / 100, 2, ',', '.') }}</p>
        </article>
        <article class="rounded-2xl bg-brand-900 p-4 text-white">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-200">Falta receber</p>
            <p class="mt-2 text-2xl font-black">R$ {{ number_format($open_balance_cents / 100, 2, ',', '.') }}</p>
        </article>
        <article @class([
            'rounded-2xl border p-4',
            'border-slate-200 bg-slate-50' => $available_credit_cents === null || $available_credit_cents >= 0,
            'border-red-200 bg-red-50' => $available_credit_cents !== null && $available_credit_cents < 0,
        ])>
            <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Limite disponível</p>
            @if ($available_credit_cents === null)
                <p class="mt-2 text-xl font-black text-slate-800">Sem limite</p>
            @elseif ($available_credit_cents >= 0)
                <p class="mt-2 text-2xl font-black text-slate-900">R$ {{ number_format($available_credit_cents / 100, 2, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">de R$ {{ number_format($customer->credit_limit_cents / 100, 2, ',', '.') }}</p>
            @else
                <p class="mt-2 text-xl font-black text-red-900">Excedido em R$ {{ number_format(abs($available_credit_cents) / 100, 2, ',', '.') }}</p>
            @endif
        </article>
    </section>

    <div class="flex flex-wrap gap-3 text-sm text-slate-600">
        <span class="rounded-full bg-slate-100 px-3 py-1.5 font-semibold">{{ $annotations_count }} {{ $annotations_count === 1 ? 'anotação' : 'anotações' }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1.5 font-semibold">
            Último consumo: {{ $last_consumption_at ? \Illuminate\Support\Carbon::parse($last_consumption_at)->format('d/m/Y H:i') : 'nenhum' }}
        </span>
    </div>

    <section>
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-bold text-slate-950">Consumos recentes</h3>
            @if ($open_balance_cents > 0)
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Ainda tem valor a pagar</span>
            @endif
        </div>

        <div class="mt-3 grid gap-3">
            @forelse ($recent_receivables as $receivable)
                <article class="rounded-2xl border border-brand-100 bg-brand-50/60 p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ $receivable->tab?->card?->code ?? 'Comanda' }}</p>
                            <p class="text-xs text-slate-500">{{ $receivable->created_at->format('d/m/Y H:i') }} · {{ $receivable->branch->name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-brand-900">R$ {{ number_format($receivable->amount_cents / 100, 2, ',', '.') }}</p>
                            <p class="text-xs font-semibold {{ $receivable->status === \App\ReceivableStatus::Paid ? 'text-emerald-700' : 'text-amber-700' }}">{{ $receivable->status->label() }}</p>
                        </div>
                    </div>
                    @include('receivables.partials.consumption-items', ['receivable' => $receivable, 'customer' => $customer])
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Nenhum consumo anotado para este cliente.</div>
            @endforelse
        </div>
    </section>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
        <a href="{{ route('customers.edit', $customer) }}" class="rounded-xl border border-brand-200 px-5 py-3 text-center text-sm font-bold text-brand-800 hover:bg-brand-50">Editar cliente</a>
        <a href="{{ route('receivables.show', $customer) }}" class="rounded-xl bg-brand-700 px-5 py-3 text-center text-sm font-bold text-white hover:bg-brand-800">Abrir extrato completo</a>
    </div>
</div>
