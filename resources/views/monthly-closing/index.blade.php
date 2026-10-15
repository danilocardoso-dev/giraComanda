@extends('layouts.app')

@section('title', 'Fechamento mensal')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Resumos dos clientes</h2>
            <p class="mt-1 text-sm text-slate-600">Prepare e acompanhe o envio dos resumos de <span class="capitalize">{{ $period_label }}</span>.</p>
        </div>
        <form method="GET" action="{{ route('monthly-closing.index') }}" class="flex items-end gap-3 rounded-2xl border border-brand-100 bg-white p-3 shadow-sm">
            <div>
                <label for="month" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Mês</label>
                <x-month-select id="month" name="month" :value="$month" class="mt-1 w-full" />
                <x-input-error :messages="$errors->get('month')" />
            </div>
            <button class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800">Exibir</button>
        </form>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">Anotado no mês</p><p class="mt-3 text-2xl font-black text-brand-900">R$ {{ number_format($charges_total_cents / 100, 2, ',', '.') }}</p></article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-sm font-semibold text-emerald-800">Recebido no mês</p><p class="mt-3 text-2xl font-black text-emerald-950">R$ {{ number_format($payments_total_cents / 100, 2, ',', '.') }}</p></article>
        <article class="rounded-2xl bg-brand-900 p-5 text-white shadow-sm"><p class="text-sm font-semibold text-brand-200">Total que falta receber</p><p class="mt-3 text-2xl font-black">R$ {{ number_format($open_total_cents / 100, 2, ',', '.') }}</p><p class="mt-1 text-xs text-brand-200">Dívida atual de todos os clientes abaixo</p></article>
        <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm"><p class="text-sm font-semibold text-red-800">Valor atrasado</p><p class="mt-3 text-2xl font-black text-red-950">R$ {{ number_format($overdue_total_cents / 100, 2, ',', '.') }}</p><p class="mt-1 text-xs font-semibold text-red-700">A data de pagamento já passou</p></article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-sm font-semibold text-emerald-800">Ainda está no prazo</p><p class="mt-3 text-2xl font-black text-emerald-950">R$ {{ number_format($not_due_total_cents / 100, 2, ',', '.') }}</p><p class="mt-1 text-xs font-semibold text-emerald-700">A data de pagamento ainda não chegou</p></article>
        <article class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Resumos enviados</p><p class="mt-3 text-2xl font-black text-brand-950">{{ $sent_count }} de {{ $customers->total() }}</p><p class="mt-1 text-xs text-slate-500">{{ max(0, $customers->total() - $sent_count) }} ainda não enviados</p></article>
    </section>

    <section class="mt-4 rounded-2xl border border-brand-200 bg-white px-5 py-4 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Como ler estes valores</p>
        <p class="mt-2 text-sm leading-6 text-slate-700">
            Em <span class="capitalize">{{ $period_label }}</span>, foram anotados <strong>R$ {{ number_format($charges_total_cents / 100, 2, ',', '.') }}</strong>
            e recebidos <strong class="text-emerald-700">R$ {{ number_format($payments_total_cents / 100, 2, ',', '.') }}</strong>.
            Hoje, os clientes abaixo ainda devem <strong class="text-red-800">R$ {{ number_format($open_total_cents / 100, 2, ',', '.') }}</strong> no total.
        </p>
        <p class="mt-2 text-sm font-semibold text-slate-700">
            <span class="text-red-800">R$ {{ number_format($overdue_total_cents / 100, 2, ',', '.') }} atrasados</span>
            <span class="mx-1 text-slate-400">+</span>
            <span class="text-emerald-800">R$ {{ number_format($not_due_total_cents / 100, 2, ',', '.') }} no prazo</span>
            <span class="mx-1 text-slate-400">=</span>
            R$ {{ number_format($open_total_cents / 100, 2, ',', '.') }} para receber.
        </p>
        <p class="mt-1 text-xs text-slate-500">O valor atual também pode incluir dívidas de meses anteriores. A lista abaixo mostra a conta de cada cliente.</p>
    </section>

    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
        Abra o WhatsApp, confirme o envio e depois use <strong>Marcar como enviado</strong>. O sistema não envia mensagens automaticamente sem sua confirmação.
    </div>

    <div class="mt-6 grid gap-4 lg:hidden">
        @forelse ($customers as $customer)
            @php($delivery = $customer->monthlyStatementDeliveries->first())
            <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-black text-slate-950">{{ $customer->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $customer->formatted_phone ?? 'Telefone não cadastrado' }}</p>
                    </div>
                    @if ($delivery)
                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Enviado</span>
                    @else
                        <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Não enviado</span>
                    @endif
                </div>

                <div class="mt-4 rounded-xl bg-brand-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Falta receber</p>
                    <p class="mt-1 text-2xl font-black text-brand-950">R$ {{ number_format($customer->open_balance_cents / 100, 2, ',', '.') }}</p>
                    <div class="mt-3 grid gap-3">
                        @if ((int) $customer->overdue_balance_cents > 0)
                            <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2">
                                <p class="text-sm font-black text-red-800">R$ {{ number_format($customer->overdue_balance_cents / 100, 2, ',', '.') }} atrasado</p>
                                <p class="mt-0.5 text-xs font-semibold text-red-700">Venceu em {{ \Illuminate\Support\Carbon::parse($customer->oldest_overdue_date)->format('d/m/Y') }}</p>
                            </div>
                        @endif
                        @if ((int) $customer->not_due_balance_cents > 0)
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                                <p class="text-sm font-black text-emerald-800">R$ {{ number_format($customer->not_due_balance_cents / 100, 2, ',', '.') }} no prazo</p>
                                <p class="mt-0.5 text-xs font-semibold text-emerald-700">Vence em {{ \Illuminate\Support\Carbon::parse($customer->next_not_due_date)->format('d/m/Y') }}</p>
                            </div>
                        @endif
                        @if ((int) $customer->open_balance_cents === 0)
                            <p class="text-sm font-semibold text-slate-500">Nada a receber hoje</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-600">
                    <div><p class="text-xs text-slate-500">Anterior</p><p class="font-bold">R$ {{ number_format($customer->opening_balance_cents / 100, 2, ',', '.') }}</p></div>
                    <div><p class="text-xs text-slate-500">Anotado no mês</p><p class="font-bold">R$ {{ number_format($customer->period_charges_cents / 100, 2, ',', '.') }}</p></div>
                    <div><p class="text-xs text-slate-500">Recebido no mês</p><p class="font-bold text-emerald-700">R$ {{ number_format($customer->period_payments_cents / 100, 2, ',', '.') }}</p></div>
                    <div><p class="text-xs text-slate-500">Final do mês</p><p class="font-bold text-brand-900">R$ {{ number_format($customer->closing_balance_cents / 100, 2, ',', '.') }}</p></div>
                </div>

                @if ($delivery)
                    <p class="mt-4 text-xs text-slate-500">Enviado em {{ $delivery->sent_at->format('d/m/Y H:i') }} por {{ $delivery->sender?->name ?? 'usuário removido' }}</p>
                @endif

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <a href="{{ route('receivables.statement', ['customer' => $customer, 'month' => $month]) }}" target="_blank" class="rounded-lg border border-brand-200 px-3 py-2.5 text-center text-sm font-bold text-brand-800 hover:bg-brand-50">Abrir resumo</a>
                    @if ($whatsappUrls[$customer->id])
                        <a href="{{ $whatsappUrls[$customer->id] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-emerald-600 px-3 py-2.5 text-center text-sm font-bold text-white hover:bg-emerald-700">Abrir WhatsApp</a>
                        @unless ($delivery)
                            <form method="POST" action="{{ route('monthly-closing.sent.store', $customer) }}" class="sm:col-span-2">
                                @csrf
                                <input type="hidden" name="month" value="{{ $month }}">
                                <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Marcar como enviado</button>
                            </form>
                        @endunless
                    @else
                        <span class="rounded-lg bg-slate-100 px-3 py-2.5 text-center text-sm font-semibold text-slate-500">Sem WhatsApp</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-brand-100 bg-white px-5 py-12 text-center text-slate-500 shadow-sm">Nenhum cliente com movimentação ou valor a receber neste mês.</div>
        @endforelse
    </div>

    <div class="mt-6 hidden overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm lg:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr><th class="px-5 py-4">Cliente</th><th class="px-5 py-4">Resumo do mês</th><th class="px-5 py-4">Falta receber</th><th class="px-5 py-4">Vencimento</th><th class="px-5 py-4">Envio</th><th class="px-5 py-4 text-right">Ações</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        @php($delivery = $customer->monthlyStatementDeliveries->first())
                        <tr class="align-top hover:bg-brand-50/50">
                            <td class="px-5 py-4">
                                <p class="font-bold text-slate-950">{{ $customer->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $customer->formatted_phone ?? 'Telefone não cadastrado' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <p>Anterior: <strong>R$ {{ number_format($customer->opening_balance_cents / 100, 2, ',', '.') }}</strong></p>
                                <p class="mt-1">Anotações: <strong>R$ {{ number_format($customer->period_charges_cents / 100, 2, ',', '.') }}</strong> · Pagamentos: <strong class="text-emerald-700">R$ {{ number_format($customer->period_payments_cents / 100, 2, ',', '.') }}</strong></p>
                                <p class="mt-1 font-bold text-brand-900">Final do mês: R$ {{ number_format($customer->closing_balance_cents / 100, 2, ',', '.') }}</p>
                            </td>
                            <td class="px-5 py-4 text-lg font-black text-slate-950">R$ {{ number_format($customer->open_balance_cents / 100, 2, ',', '.') }}</td>
                            <td class="min-w-48 px-5 py-4">
                                <div class="grid gap-2">
                                    @if ((int) $customer->overdue_balance_cents > 0)
                                        <div>
                                            <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-black text-red-800">R$ {{ number_format($customer->overdue_balance_cents / 100, 2, ',', '.') }} atrasado</span>
                                            <p class="mt-1 text-xs font-semibold text-red-700">Venceu em {{ \Illuminate\Support\Carbon::parse($customer->oldest_overdue_date)->format('d/m/Y') }}</p>
                                        </div>
                                    @endif
                                    @if ((int) $customer->not_due_balance_cents > 0)
                                        <div>
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800">R$ {{ number_format($customer->not_due_balance_cents / 100, 2, ',', '.') }} no prazo</span>
                                            <p class="mt-1 text-xs font-semibold text-emerald-700">Vence em {{ \Illuminate\Support\Carbon::parse($customer->next_not_due_date)->format('d/m/Y') }}</p>
                                        </div>
                                    @endif
                                    @if ((int) $customer->open_balance_cents === 0)
                                        <span class="text-xs font-semibold text-slate-500">Nada a receber hoje</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($delivery)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Enviado</span>
                                    <p class="mt-2 text-xs text-slate-500">{{ $delivery->sent_at->format('d/m/Y H:i') }}<br>por {{ $delivery->sender?->name ?? 'usuário removido' }}</p>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Não enviado</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-col items-stretch gap-2 sm:min-w-48">
                                    <a href="{{ route('receivables.statement', ['customer' => $customer, 'month' => $month]) }}" target="_blank" class="rounded-lg border border-brand-200 px-3 py-2 text-center text-xs font-bold text-brand-800 hover:bg-brand-50">Abrir resumo</a>
                                    @if ($whatsappUrls[$customer->id])
                                        <a href="{{ $whatsappUrls[$customer->id] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-emerald-600 px-3 py-2 text-center text-xs font-bold text-white hover:bg-emerald-700">Abrir WhatsApp</a>
                                        @unless ($delivery)
                                            <form method="POST" action="{{ route('monthly-closing.sent.store', $customer) }}">
                                                @csrf
                                                <input type="hidden" name="month" value="{{ $month }}">
                                                <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">Marcar como enviado</button>
                                            </form>
                                        @endunless
                                    @else
                                        <span class="rounded-lg bg-slate-100 px-3 py-2 text-center text-xs font-semibold text-slate-500">Sem WhatsApp</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Nenhum cliente com movimentação ou valor a receber neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $customers->links() }}</div>
@endsection
