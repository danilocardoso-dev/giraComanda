@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Clientes com anotação</h2>
            <p class="mt-1 text-sm text-slate-600">Clique em um cliente para ver consumo, saldo e limite disponível.</p>
        </div>
        <a href="{{ route('customers.create') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Novo cliente</a>
    </div>

    <div class="mt-6 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap gap-2">
            @foreach (['all' => 'Todos', 'active' => 'Ativos', 'inactive' => 'Inativos'] as $filter => $label)
                <a href="{{ route('customers.index', ['status' => $filter, 'q' => $search ?: null]) }}" @class([
                    'rounded-xl px-4 py-2 text-sm font-bold',
                    'bg-brand-700 text-white' => $status === $filter,
                    'bg-brand-50 text-brand-800 hover:bg-brand-100' => $status !== $filter,
                ])>{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('customers.index') }}" class="mt-4 flex gap-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <input name="q" value="{{ $search }}" placeholder="Buscar por nome, CPF ou telefone"
                class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Buscar</button>
        </form>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr>
                        <th class="px-5 py-4">Cliente</th>
                        <th class="px-5 py-4">CPF</th>
                        <th class="px-5 py-4">Vencimento</th>
                        <th class="px-5 py-4">Limite</th>
                        <th class="px-5 py-4">Situação</th>
                        <th class="px-5 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr data-customer-summary-url="{{ route('customers.summary', $customer) }}" tabindex="0" role="button" aria-label="Ver resumo de {{ $customer->name }}" class="cursor-pointer hover:bg-brand-50/60 focus:bg-brand-50 focus:outline-none">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ $customer->name }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $customer->formatted_phone ?: 'Sem telefone' }}</div>
                            </td>
                            <td class="px-5 py-4 font-mono text-slate-600">{{ $customer->masked_cpf }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $customer->due_day ? 'Dia '.$customer->due_day : 'Padrão geral' }}</td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ $customer->credit_limit_cents === null ? 'Sem limite' : 'R$ '.number_format($customer->credit_limit_cents / 100, 2, ',', '.') }}
                            </td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-100 text-emerald-800' => $customer->active,
                                    'bg-slate-200 text-slate-700' => ! $customer->active,
                                ])>{{ $customer->active ? 'Ativo' : 'Inativo' }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end gap-3">
                                    <button type="button" data-customer-summary-open="{{ route('customers.summary', $customer) }}" class="font-bold text-brand-700 hover:text-brand-900">Resumo</button>
                                    <a href="{{ route('customers.edit', $customer) }}" class="font-bold text-slate-600 hover:text-slate-900">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Nenhum cliente encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $customers->links() }}</div>

    <dialog data-customer-summary-modal class="m-auto max-h-[90vh] w-[min(960px,calc(100%-2rem))] overflow-y-auto rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
        <div data-customer-summary-content class="p-5 sm:p-7">
            <p class="py-12 text-center text-sm font-semibold text-slate-500">Carregando resumo...</p>
        </div>
    </dialog>
@endsection
