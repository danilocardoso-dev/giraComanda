@extends('layouts.app')

@section('title', 'Extrato de '.$customer->name)

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('receivables.index') }}" class="text-sm font-bold text-brand-700 hover:text-brand-900">← Voltar para contas a receber</a>
            <h2 class="mt-3 text-2xl font-bold text-slate-950">{{ $customer->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $customer->formatted_cpf }}{{ $customer->formatted_phone ? ' · '.$customer->formatted_phone : '' }}</p>
        </div>
        <div class="flex flex-col gap-3">
            <div class="rounded-2xl bg-brand-900 px-6 py-4 text-white">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-200">Falta receber deste cliente</p>
                <p class="mt-1 text-3xl font-black">R$ {{ number_format($openBalanceCents / 100, 2, ',', '.') }}</p>
            </div>
        </div>
    </div>

    @if ($openBalanceCents > 0)
        <section class="mt-7 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6">
            <h3 class="text-lg font-bold text-emerald-950">Registrar recebimento</h3>
            <p class="mt-1 text-sm text-emerald-800">O valor será abatido das anotações mais antigas e lançado no movimento do caixa.</p>
            <form method="POST" action="{{ route('receivables.payments.store', $customer) }}" class="mt-4 grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)_auto] sm:items-start">
                @csrf
                <div>
                    <label for="method" class="text-sm font-semibold text-emerald-950">Forma de recebimento</label>
                    <select id="method" name="method" required class="mt-2 w-full rounded-xl border border-emerald-200 bg-white px-4 py-3 outline-none focus:border-emerald-500 focus:ring-3 focus:ring-emerald-500/20">
                        <option value="">Selecione</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('method')" />
                </div>
                <div>
                    <label for="amount" class="text-sm font-semibold text-emerald-950">Valor recebido</label>
                    <div class="mt-2 flex rounded-xl border border-emerald-200 bg-white focus-within:border-emerald-500 focus-within:ring-3 focus-within:ring-emerald-500/20">
                        <span class="border-r border-emerald-100 px-4 py-3 text-emerald-700">R$</span>
                        <input id="amount" name="amount" value="{{ old('amount') }}" data-mask="currency" inputmode="numeric" placeholder="0,00" required class="min-w-0 flex-1 rounded-r-xl px-4 py-3 outline-none">
                    </div>
                    <x-input-error :messages="$errors->get('amount')" />
                </div>
                <button type="submit" class="rounded-xl bg-emerald-700 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-800">Dar baixa</button>
            </form>
        </section>
    @endif

    <section class="mt-7 rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('receivables.show', $customer) }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div>
                <label for="month" class="text-sm font-semibold text-slate-700">Mês do extrato</label>
                <x-month-select id="month" name="month" :value="$month" class="mt-2 w-full" />
            </div>
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Exibir período</button>
            <a href="{{ route('receivables.statement', ['customer' => $customer, 'month' => $month]) }}" target="_blank" class="rounded-xl border border-brand-200 bg-white px-5 py-2.5 text-center text-sm font-bold text-brand-800 hover:bg-brand-50">Imprimir resumo</a>
            @if ($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-center text-sm font-bold text-white hover:bg-emerald-700">Enviar no WhatsApp</a>
            @else
                <span class="rounded-xl bg-slate-100 px-5 py-2.5 text-center text-sm font-semibold text-slate-500" title="Cadastre o telefone do cliente para usar o WhatsApp">WhatsApp sem telefone</span>
            @endif
        </form>
    </section>

    <div class="mt-6">
        <h3 class="text-xl font-bold capitalize text-slate-950">{{ $period_label }}</h3>
        <p class="mt-1 text-sm text-slate-500">Resumo completo das anotações e pagamentos do período.</p>
    </div>

    <section class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">Saldo anterior</p><p class="mt-2 text-2xl font-black text-slate-900">R$ {{ number_format($opening_balance_cents / 100, 2, ',', '.') }}</p></article>
        <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">Novas anotações</p><p class="mt-2 text-2xl font-black text-brand-900">R$ {{ number_format($charges_total_cents / 100, 2, ',', '.') }}</p></article>
        <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">Pagamentos no mês</p><p class="mt-2 text-2xl font-black text-emerald-700">R$ {{ number_format($payments_total_cents / 100, 2, ',', '.') }}</p></article>
        <article class="rounded-2xl bg-brand-900 p-5 text-white shadow-sm"><p class="text-sm font-semibold text-brand-200">Saldo ao final do mês</p><p class="mt-2 text-2xl font-black">R$ {{ number_format($closing_balance_cents / 100, 2, ',', '.') }}</p></article>
    </section>

    <div class="mt-7">
        <h3 class="text-lg font-bold text-slate-950">Anotações do período</h3>
    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr>
                        <th class="px-5 py-4">Data</th>
                        <th class="px-5 py-4">Comanda</th>
                        <th class="px-5 py-4">Vencimento</th>
                        <th class="px-5 py-4">Situação</th>
                        <th class="px-5 py-4 text-right">Anotado</th>
                        <th class="px-5 py-4 text-right">Recebido</th>
                        <th class="px-5 py-4 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($receivables as $receivable)
                        <tr class="hover:bg-brand-50/60">
                            <td class="px-5 py-4 text-slate-600">{{ $receivable->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4">
                                @if ($receivable->branch_id === $currentBranch->id && $receivable->tab)
                                    <a href="{{ route('tabs.show', $receivable->tab) }}" class="font-bold text-brand-700 hover:text-brand-900">{{ $receivable->tab->card?->code ?? 'Comanda' }}</a>
                                @else
                                    <span class="font-bold text-slate-800">{{ $receivable->tab?->card?->code ?? 'Comanda' }}</span>
                                @endif
                                @if ($currentCompany->customer_sharing_enabled)
                                    <p class="mt-1 text-xs text-slate-500">{{ $receivable->branch->name }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $receivable->due_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-amber-100 text-amber-800' => $receivable->status === \App\ReceivableStatus::Open,
                                    'bg-emerald-100 text-emerald-800' => $receivable->status === \App\ReceivableStatus::Paid,
                                ])>{{ $receivable->status->label() }}</span>
                                @if ($receivable->status === \App\ReceivableStatus::Open && $receivable->paid_cents > 0)
                                    <span class="mt-1 block text-xs font-semibold text-amber-700">Pagamento parcial</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-700">R$ {{ number_format($receivable->amount_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-5 py-4 text-right font-semibold text-emerald-700">R$ {{ number_format($receivable->paid_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-5 py-4 text-right text-lg font-black text-slate-950">R$ {{ number_format(($receivable->amount_cents - $receivable->paid_cents) / 100, 2, ',', '.') }}</td>
                        </tr>
                        <tr class="bg-brand-50/60">
                            <td colspan="7" class="px-5 py-4">
                                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-brand-700">Itens desta anotação</p>
                                @include('receivables.partials.consumption-items', ['receivable' => $receivable, 'customer' => $customer])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">Nenhuma anotação neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-7">
        <h3 class="text-lg font-bold text-slate-950">Pagamentos do período</h3>
    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-emerald-50 text-left text-xs font-bold uppercase tracking-wider text-emerald-800">
                    <tr><th class="px-5 py-4">Data</th><th class="px-5 py-4">Forma</th><th class="px-5 py-4">Registrado por</th><th class="px-5 py-4">Filial</th><th class="px-5 py-4 text-right">Valor</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-emerald-50/60">
                            <td class="px-5 py-4 text-slate-600">{{ $payment->paid_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $payment->method->label() }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $payment->recorder->name }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $payment->branch->name }}</td>
                            <td class="px-5 py-4 text-right text-lg font-black text-emerald-700">R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Nenhum pagamento neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
