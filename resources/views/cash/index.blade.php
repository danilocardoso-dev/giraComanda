@extends('layouts.app')

@section('title', 'Caixa')

@section('content')
    <section>
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Controle do caixa</h2>
            <p class="mt-1 text-sm text-slate-600">Abra o caixa antes dos recebimentos e confira o dinheiro ao encerrar o turno.</p>
        </div>

        @if ($openCashSession)
            <div class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800">Caixa aberto</span>
                        <p class="mt-3 font-bold text-slate-950">Aberto por {{ $openCashSession->opener->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $openCashSession->opened_at->format('d/m/Y \à\s H:i') }}</p>
                    </div>
                    <div class="rounded-2xl bg-brand-900 px-6 py-4 text-white">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-200">Dinheiro esperado</p>
                        <p class="mt-1 text-3xl font-black">R$ {{ number_format($expectedCashCents / 100, 2, ',', '.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl bg-white p-4"><p class="text-xs font-bold uppercase text-slate-500">Abertura</p><p class="mt-2 text-xl font-black text-slate-900">R$ {{ number_format($openCashSession->opening_amount_cents / 100, 2, ',', '.') }}</p></div>
                    <div class="rounded-2xl bg-white p-4"><p class="text-xs font-bold uppercase text-slate-500">Vendas em dinheiro</p><p class="mt-2 text-xl font-black text-slate-900">R$ {{ number_format($cashPaymentsCents / 100, 2, ',', '.') }}</p></div>
                    <div class="rounded-2xl bg-white p-4"><p class="text-xs font-bold uppercase text-slate-500">Suprimentos</p><p class="mt-2 text-xl font-black text-emerald-700">+ R$ {{ number_format($suppliesCents / 100, 2, ',', '.') }}</p></div>
                    <div class="rounded-2xl bg-white p-4"><p class="text-xs font-bold uppercase text-slate-500">Sangrias</p><p class="mt-2 text-xl font-black text-red-700">− R$ {{ number_format($withdrawalsCents / 100, 2, ',', '.') }}</p></div>
                </div>

                <div class="mt-6 grid gap-5 xl:grid-cols-2">
                    <form method="POST" action="{{ route('cash.movements.store', $openCashSession) }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                        @csrf
                        <h3 class="text-lg font-bold text-slate-950">Suprimento ou sangria</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="type" class="text-sm font-semibold text-slate-700">Tipo</label>
                                <select id="type" name="type" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5" required>
                                    @foreach ($movementTypes as $type)
                                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('type') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="movement_amount" class="text-sm font-semibold text-slate-700">Valor</label>
                                <input id="movement_amount" name="amount" type="text" inputmode="decimal" data-mask="currency" value="{{ old('amount') }}" placeholder="0,00" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5" required>
                                @error('amount') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="description" class="text-sm font-semibold text-slate-700">Motivo</label>
                            <input id="description" name="description" value="{{ old('description') }}" maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5" placeholder="Ex.: troco adicional ou retirada para depósito" required>
                            @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <button class="mt-4 rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800">Registrar movimento</button>
                    </form>

                    <form method="POST" action="{{ route('cash.sessions.close', $openCashSession) }}" class="rounded-2xl border border-red-200 bg-white p-5">
                        @csrf
                        <h3 class="text-lg font-bold text-slate-950">Fechar e conferir caixa</h3>
                        <p class="mt-1 text-sm text-slate-500">Conte todo o dinheiro físico que permaneceu no caixa.</p>
                        <div class="mt-4">
                            <label for="counted_amount" class="text-sm font-semibold text-slate-700">Dinheiro contado</label>
                            <input id="counted_amount" name="counted_amount" type="text" inputmode="decimal" data-mask="currency" value="{{ old('counted_amount') }}" placeholder="0,00" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5" required>
                            @error('counted_amount') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div class="mt-4">
                            <label for="notes" class="text-sm font-semibold text-slate-700">Observação <span class="font-normal text-slate-400">(opcional)</span></label>
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5">{{ old('notes') }}</textarea>
                        </div>
                        <button class="mt-4 rounded-xl bg-red-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-800">Fechar caixa</button>
                    </form>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('cash.sessions.store') }}" class="mt-6 max-w-xl rounded-3xl border border-brand-100 bg-white p-6 shadow-sm">
                @csrf
                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-slate-700">Caixa fechado</span>
                <h3 class="mt-4 text-xl font-bold text-slate-950">Abrir o caixa</h3>
                <p class="mt-1 text-sm text-slate-500">Informe quanto há em dinheiro para troco no início do turno.</p>
                <div class="mt-5">
                    <label for="opening_amount" class="text-sm font-semibold text-slate-700">Valor inicial</label>
                    <input id="opening_amount" name="opening_amount" type="text" inputmode="decimal" data-mask="currency" value="{{ old('opening_amount') }}" placeholder="0,00" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5" required autofocus>
                    @error('opening_amount') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <button class="mt-5 rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800">Abrir caixa</button>
            </form>
        @endif
    </section>

    <section class="mt-10 border-t border-slate-200 pt-8">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Histórico do dia</p>
                <h2 class="mt-2 text-2xl font-bold text-slate-950">Entradas recebidas</h2>
                <p class="mt-1 text-sm text-slate-600">Tudo o que realmente foi pago em comandas ou em anotações de clientes.</p>
            </div>
            <form method="GET" action="{{ route('cash.index') }}" class="flex flex-col gap-3 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="grow">
                    <label for="date" class="text-xs font-bold uppercase tracking-wider text-slate-500">Dia consultado</label>
                    <input id="date" name="date" type="date" value="{{ $date }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                </div>
                <button class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800">Exibir</button>
            </form>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl bg-brand-900 p-5 text-white shadow-sm sm:col-span-2 xl:col-span-1">
                <p class="text-sm font-semibold text-brand-200">Total recebido</p>
                <p class="mt-3 text-3xl font-black">R$ {{ number_format($totalCents / 100, 2, ',', '.') }}</p>
                <p class="mt-1 text-xs text-brand-200">{{ $payments->total() }} {{ $payments->total() === 1 ? 'entrada registrada' : 'entradas registradas' }}</p>
            </article>
            @foreach ($paymentMethods as $method)
                <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-500">{{ $method->label() }}</p>
                    <p class="mt-3 text-2xl font-black text-brand-900">R$ {{ number_format(($totalsByMethod[$method->value] ?? 0) / 100, 2, ',', '.') }}</p>
                </article>
            @endforeach
        </div>

        <div class="mt-6 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4">
            <p class="font-bold text-brand-900">Esta tela é somente um histórico.</p>
                <p class="mt-1 text-sm leading-6 text-slate-600"><strong>Anotação</strong> mostra de onde veio a dívida. <strong>Dinheiro, PIX ou cartão</strong> mostra como ela foi paga e entrou no caixa. Para cobrar um saldo que ainda está aberto, use <strong>Contas a receber</strong>.</p>
        </div>

        <div class="mt-7 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
            <div>
                <h3 class="text-xl font-bold text-slate-950">Recebimentos de {{ $selectedDate->format('d/m/Y') }}</h3>
                <p class="mt-1 text-sm text-slate-500">Registro informativo: nenhuma linha inicia uma nova cobrança.</p>
            </div>
            <span class="text-sm font-semibold text-slate-500">{{ $payments->total() }} {{ $payments->total() === 1 ? 'recebimento' : 'recebimentos' }}</span>
        </div>

        <div class="mt-4 grid gap-4 lg:hidden">
            @forelse ($payments as $payment)
                <article tabindex="0" role="button" data-cash-payment-detail-url="{{ route('cash.payments.show', $payment) }}" class="cursor-pointer rounded-2xl border border-brand-100 bg-white p-5 text-left shadow-sm transition hover:border-brand-300 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-brand-500/20">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $payment->paid_at->format('H:i') }} · Recebimento #{{ $payment->id }}</p>
                            @if ($payment->tab)
                                <p class="mt-2 font-black text-slate-950">Comanda {{ $payment->tab->card->code }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $payment->tab->customer?->name ?? 'Cliente não identificado' }}</p>
                            @else
                                <p class="mt-2 font-black text-slate-950">Anotação paga</p>
                                <p class="mt-1 text-sm text-slate-500">Cliente: {{ $payment->customer->name }}</p>
                            @endif
                        </div>
                        <p class="shrink-0 text-xl font-black text-brand-950">R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</p>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                        <div><p class="text-xs text-slate-500">Recebido por</p><p class="mt-1 font-bold text-slate-800">{{ $payment->method->label() }}</p></div>
                        <div><p class="text-xs text-slate-500">Registrado por</p><p class="mt-1 font-bold text-slate-800">{{ $payment->recorder->name }}</p></div>
                    </div>
                    @unless ($payment->tab)
                        <p class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">Pagamento concluído e já baixado da conta do cliente.</p>
                    @endunless
                    <button type="button" data-cash-payment-detail-open="{{ route('cash.payments.show', $payment) }}" class="mt-4 w-full rounded-xl border border-brand-200 px-4 py-2.5 text-sm font-bold text-brand-800 hover:bg-brand-50">Ver itens vendidos</button>
                </article>
            @empty
                <div class="rounded-2xl border border-brand-100 bg-white px-5 py-12 text-center text-slate-500 shadow-sm">Nenhum recebimento nesta data.</div>
            @endforelse
        </div>

        <div class="mt-4 hidden overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm lg:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr>
                        <th class="px-5 py-4">Horário</th>
                        <th class="px-5 py-4">Origem do recebimento</th>
                        <th class="px-5 py-4">Recebido por</th>
                        <th class="px-5 py-4">Registrado por</th>
                        <th class="px-5 py-4 text-right">Valor recebido</th>
                        <th class="px-5 py-4 text-right"><span class="sr-only">Detalhes</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr tabindex="0" role="button" data-cash-payment-detail-url="{{ route('cash.payments.show', $payment) }}" class="cursor-pointer transition hover:bg-brand-50/60 focus:bg-brand-50/60 focus:outline-none">
                            <td class="px-5 py-4 text-slate-600">
                                <span class="font-semibold text-slate-800">{{ $payment->paid_at->format('H:i') }}</span>
                                <span class="mt-1 block text-xs text-slate-400">#{{ $payment->id }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($payment->tab)
                                    <span class="inline-flex rounded-full bg-brand-100 px-2.5 py-1 text-xs font-bold text-brand-800">Comanda {{ $payment->tab->card->code }}</span>
                                    <span class="mt-2 block text-sm text-slate-600">{{ $payment->tab->customer?->name ?? 'Cliente não identificado' }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Anotação paga</span>
                                    <span class="mt-2 block font-semibold text-slate-800">{{ $payment->customer->name }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">Pagamento concluído e já baixado</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $payment->method->label() }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $payment->recorder->name }}</td>
                            <td class="px-5 py-4 text-right text-lg font-black text-slate-950">R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" data-cash-payment-detail-open="{{ route('cash.payments.show', $payment) }}" class="whitespace-nowrap rounded-lg border border-brand-200 px-3 py-2 text-xs font-bold text-brand-800 hover:bg-white">Ver itens</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Nenhum recebimento nesta data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $payments->links() }}</div>
    </section>

    <dialog data-cash-payment-detail-modal class="m-auto max-h-[90vh] w-[min(900px,calc(100%-2rem))] overflow-y-auto rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
        <div data-cash-payment-detail-content class="p-5 sm:p-7">
            <p class="py-12 text-center text-sm font-semibold text-slate-500">Carregando detalhes...</p>
        </div>
    </dialog>

    <section class="mt-10 border-t border-slate-200 pt-8">
        <h2 class="text-xl font-bold text-slate-950">Últimos caixas</h2>
        <div class="mt-4 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                        <tr><th class="px-5 py-4">Abertura</th><th class="px-5 py-4">Responsável</th><th class="px-5 py-4">Fechamento</th><th class="px-5 py-4 text-right">Esperado</th><th class="px-5 py-4 text-right">Diferença</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentCashSessions as $cashSession)
                            <tr>
                                <td class="px-5 py-4"><span class="font-semibold text-slate-800">{{ $cashSession->opened_at->format('d/m/Y H:i') }}</span><span class="block text-xs text-slate-500">R$ {{ number_format($cashSession->opening_amount_cents / 100, 2, ',', '.') }} inicial</span></td>
                                <td class="px-5 py-4 text-slate-700">{{ $cashSession->opener->name }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $cashSession->closed_at?->format('d/m/Y H:i') ?? 'Em andamento' }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-800">{{ $cashSession->expected_amount_cents === null ? '—' : 'R$ '.number_format($cashSession->expected_amount_cents / 100, 2, ',', '.') }}</td>
                                <td class="px-5 py-4 text-right font-bold {{ ($cashSession->difference_cents ?? 0) < 0 ? 'text-red-700' : (($cashSession->difference_cents ?? 0) > 0 ? 'text-emerald-700' : 'text-slate-500') }}">{{ $cashSession->difference_cents === null ? '—' : (($cashSession->difference_cents > 0 ? '+' : '').' R$ '.number_format($cashSession->difference_cents / 100, 2, ',', '.')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Nenhum caixa registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
