@extends('layouts.platform')

@section('title', $company->name)

@section('content')
    @php
        $invoices = $subscription->invoices->sortByDesc('due_date');
        $overdueInvoices = $invoices->filter(fn ($invoice) => $invoice->paid_at === null && $invoice->due_date->isBefore(today()));
        $nextInvoice = $invoices->filter(fn ($invoice) => $invoice->paid_at === null)->sortBy('due_date')->first();
        $statusLabel = $subscription->statusLabel();
        $reviewLabels = [
            'test_payment_not_applied' => 'Simulação confirmada, sem baixa real.',
            'refund_or_chargeback' => 'Estorno ou contestação: revise o acesso e a mensalidade.',
            'amount_currency_or_method_mismatch' => 'Valor, moeda ou forma de pagamento divergente.',
            'invoice_already_paid_or_changed' => 'Mensalidade já paga ou alterada: confira possível pagamento em duplicidade.',
            'conflicting_billing_cycle' => 'Já existe uma mensalidade na competência do pagamento.',
            'access_kept_blocked_by_admin' => 'Pagamento registrado; bloqueio administrativo preservado.',
        ];
    @endphp

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('platform.companies.index') }}" class="text-sm font-bold text-slate-500 hover:text-slate-950">← Todos os clientes</a>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h2 class="text-3xl font-black text-slate-950">{{ $company->name }}</h2>
                <span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-emerald-100 text-emerald-800' => $statusLabel === 'Em dia', 'bg-amber-100 text-amber-800' => $statusLabel === 'Em teste', 'bg-red-100 text-red-800' => in_array($statusLabel, ['Inadimplente', 'Suspenso', 'Teste encerrado'], true), 'bg-slate-200 text-slate-700' => $statusLabel === 'Cancelado'])>{{ $statusLabel }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-600">Conta criada em {{ $company->created_at->format('d/m/Y') }} · {{ $company->branches->count() }} {{ $company->branches->count() === 1 ? 'filial' : 'filiais' }}</p>
        </div>
        <form method="POST" action="{{ route('platform.support.store', $company) }}" class="flex flex-col gap-2 sm:flex-row">
            @csrf
            <select name="branch_id" required class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold">
                @foreach ($company->branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}{{ $branch->active ? '' : ' · inativa' }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-amber-400 px-5 py-3 text-sm font-black text-slate-950 hover:bg-amber-300">Acessar como suporte</button>
        </form>
    </div>

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl bg-slate-950 p-5 text-white shadow-sm"><p class="text-sm text-slate-300">Valor mensal</p><p class="mt-2 text-3xl font-black">R$ {{ number_format($subscription->monthly_amount_cents / 100, 2, ',', '.') }}</p><p class="mt-2 text-xs text-slate-400">Vencimento todo dia {{ $subscription->billing_day }}</p></article>
        <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm"><p class="text-sm font-semibold text-red-800">Total vencido</p><p class="mt-2 text-3xl font-black text-red-950">R$ {{ number_format($overdueInvoices->sum('amount_cents') / 100, 2, ',', '.') }}</p><p class="mt-2 text-xs text-red-700">{{ $overdueInvoices->count() }} {{ $overdueInvoices->count() === 1 ? 'mensalidade atrasada' : 'mensalidades atrasadas' }}</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">{{ $nextInvoice?->due_date->isBefore(today()) ? 'Vencimento em atraso' : 'Próximo vencimento' }}</p><p class="mt-2 text-2xl font-black text-slate-950">{{ $nextInvoice?->due_date->format('d/m/Y') ?? 'Sem cobrança' }}</p><p class="mt-2 text-xs text-slate-500">{{ $nextInvoice ? 'R$ '.number_format($nextInvoice->amount_cents / 100, 2, ',', '.') : 'Nenhuma mensalidade pendente' }}</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">Operação do cliente</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $openTabsCount }}</p><p class="mt-2 text-xs text-slate-500">comandas abertas agora</p></article>
    </section>

    <div class="mt-7 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-lg font-black text-slate-950">Assinatura do software</h3>
            <p class="mt-1 text-sm text-slate-500">Defina valor, vencimento e situação comercial do cliente.</p>
            <form method="POST" action="{{ route('platform.subscriptions.update', $company) }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <div><label for="status" class="text-sm font-bold text-slate-700">Situação</label><select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3">@foreach (\App\SubscriptionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status', $subscription->status->value) === $status->value)>{{ $status->label() }}</option>@endforeach</select><x-input-error :messages="$errors->get('status')" /></div>
                <div><label for="monthly_amount" class="text-sm font-bold text-slate-700">Valor por mês</label><div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white"><span class="grid place-items-center bg-slate-100 px-4 font-bold text-slate-600">R$</span><input id="monthly_amount" name="monthly_amount" data-mask="currency" inputmode="numeric" value="{{ old('monthly_amount', number_format($subscription->monthly_amount_cents / 100, 2, ',', '.')) }}" required class="min-w-0 flex-1 px-4 py-3 outline-none"></div><x-input-error :messages="$errors->get('monthly_amount')" /></div>
                <div><label for="billing_day" class="text-sm font-bold text-slate-700">Dia do vencimento</label><input id="billing_day" name="billing_day" type="number" min="1" max="28" value="{{ old('billing_day', $subscription->billing_day) }}" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><x-input-error :messages="$errors->get('billing_day')" /></div>
                <div><label for="billing_phone" class="text-sm font-bold text-slate-700">WhatsApp financeiro</label><input id="billing_phone" name="billing_phone" data-mask="phone" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000" value="{{ old('billing_phone', $subscription->billing_phone) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><x-input-error :messages="$errors->get('billing_phone')" /></div>
                <div><label for="started_at" class="text-sm font-bold text-slate-700">Início da assinatura</label><input id="started_at" name="started_at" type="date" value="{{ old('started_at', $subscription->started_at->toDateString()) }}" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><x-input-error :messages="$errors->get('started_at')" /></div>
                <div><label for="trial_ends_at" class="text-sm font-bold text-slate-700">Fim do teste</label><input id="trial_ends_at" name="trial_ends_at" type="date" value="{{ old('trial_ends_at', $subscription->trial_ends_at?->toDateString()) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><x-input-error :messages="$errors->get('trial_ends_at')" /></div>
                <div class="sm:col-span-2"><label for="notes" class="text-sm font-bold text-slate-700">Observações administrativas</label><textarea id="notes" name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3">{{ old('notes', $subscription->notes) }}</textarea><x-input-error :messages="$errors->get('notes')" /></div>
                <div class="sm:col-span-2"><button class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800">Salvar assinatura</button></div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-lg font-black text-slate-950">Proprietário e acesso</h3>
            <p class="mt-1 text-sm text-slate-500">Recupere o acesso sem visualizar a senha atual.</p>
            <div class="mt-5 space-y-4">
                @forelse ($owners as $owner)
                    <article class="rounded-xl border border-slate-200 p-4">
                        <p class="font-bold text-slate-950">{{ $owner->name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $owner->email }}</p>
                        @if ($owner->must_change_password)<p class="mt-2 text-xs font-bold text-amber-700">Aguardando troca da senha temporária</p>@endif
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-bold text-red-700">Redefinir senha</summary>
                            <form method="POST" action="{{ route('platform.owners.password.update', [$company, $owner]) }}" class="mt-4 grid gap-3">
                                @csrf
                                @method('PUT')
                                <input name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="Senha temporária" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                                <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" placeholder="Confirmar senha" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                                <x-input-error :messages="$errors->get('password')" />
                                <button class="rounded-xl bg-red-700 px-4 py-3 text-sm font-bold text-white hover:bg-red-800">Definir senha temporária</button>
                            </form>
                        </details>
                    </article>
                @empty
                    <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Nenhum proprietário ativo foi encontrado.</p>
                @endforelse
            </div>

            <div class="mt-5 border-t border-slate-100 pt-5">
                <p class="text-sm font-bold text-slate-700">Acesso geral da empresa</p>
                <form method="POST" action="{{ route('platform.companies.update', $company) }}" class="mt-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="active" value="{{ $company->active ? '0' : '1' }}">
                    <button class="rounded-xl px-4 py-3 text-sm font-bold {{ $company->active ? 'border border-red-200 text-red-700 hover:bg-red-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">{{ $company->active ? 'Desativar acesso de todos' : 'Reativar empresa' }}</button>
                </form>
            </div>
        </section>
    </div>

    <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Mercado Pago · pagamentos e recorrência</h3>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $subscription->paid_until ? 'Período pago até '.$subscription->paid_until->format('d/m/Y H:i').'.' : 'Acesso segue o status administrativo da assinatura.' }} Salvar o status Ativa no formulário acima concede liberação administrativa sem prazo. Para baixa manual, cancele antes qualquer recorrência de cartão. Suspender, cancelar ou mudar o valor da assinatura cancela a recorrência anterior no Mercado Pago.</p>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @forelse ($gatewayCheckouts as $checkout)
                <article class="rounded-xl border border-slate-200 p-4 text-sm">
                    <p class="font-bold">{{ $checkout->kind === 'card' ? 'Cartão recorrente' : 'Pix' }} · {{ $checkout->environment }} · {{ $checkout->statusLabel() }}</p>
                    <p class="mt-2 break-all text-slate-600">ID: {{ $checkout->remote_id ?? 'Nenhum ID confirmado no Mercado Pago' }}</p>
                    @if ($checkout->failure_message)<p class="mt-2 leading-6 text-red-800">{{ $checkout->failure_message }}</p>@endif
                    <div class="mt-3 flex flex-wrap gap-4">
                        <form method="POST" action="{{ route('subscription.checkouts.update', $checkout) }}">@csrf @method('PUT')<button class="min-h-11 font-bold text-brand-800 underline">Consultar Mercado Pago</button></form>
                        @if ($checkout->isRecurring() || ($checkout->kind === 'pix' && in_array($checkout->status, ['pending', 'in_process', 'creating'], true)))
                            <form method="POST" action="{{ route('subscription.checkouts.destroy', $checkout) }}">@csrf @method('DELETE')<button class="min-h-11 font-bold text-red-700 underline">{{ ! $checkout->remote_id ? 'Encerrar tentativa local' : 'Cancelar '.($checkout->kind === 'card' ? 'recorrência' : 'Pix') }}</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">Nenhum pagamento online iniciado.</p>
            @endforelse
        </div>
        <p class="mt-4 text-sm text-slate-600">Estornos e contestações são sinalizados para revisão administrativa; não geram reembolso nem alteração automática das suas decisões de acesso.</p>
        <div class="mt-5 divide-y divide-slate-100">
            @foreach ($gatewayPayments as $payment)
                <div class="py-3 text-sm"><p class="font-bold">{{ $payment->environment }} · Pagamento {{ $payment->remote_id }} · R$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }} · {{ $payment->status }}</p><p class="mt-1 {{ $payment->review_reason && $payment->review_reason !== 'test_payment_not_applied' ? 'text-red-700' : 'text-slate-600' }}">{{ $payment->review_reason ? ($reviewLabels[$payment->review_reason] ?? 'Revisão administrativa necessária.') : ($payment->applied_at ? 'Baixa automática confirmada.' : 'Ainda sem baixa automática.') }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><h3 class="text-lg font-black text-slate-950">Mensalidades</h3><p class="mt-1 text-sm text-slate-500">As cobranças são geradas automaticamente conforme o início e o dia de vencimento.</p></div><p class="text-sm font-bold text-slate-600">{{ $invoices->count() }} registros recentes</p></div>

        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><strong>Cobrança automática ativa.</strong> Use o formulário abaixo somente para lançar uma mensalidade excepcional.</div>

        <form method="POST" action="{{ route('platform.invoices.store', $company) }}" class="mt-5 grid gap-4 rounded-xl bg-slate-50 p-4 md:grid-cols-4">
            @csrf
            <div><label for="reference_month" class="text-xs font-black uppercase tracking-wide text-slate-600">Mês</label><input id="reference_month" name="reference_month" type="month" value="{{ substr((string) old('reference_month', today()->format('Y-m')), 0, 7) }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><x-input-error :messages="$errors->get('reference_month')" /></div>
            <div><label for="due_date" class="text-xs font-black uppercase tracking-wide text-slate-600">Vencimento</label><input id="due_date" name="due_date" type="date" value="{{ old('due_date', today()->day(min($subscription->billing_day, today()->daysInMonth))->toDateString()) }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><x-input-error :messages="$errors->get('due_date')" /></div>
            <div><label for="amount" class="text-xs font-black uppercase tracking-wide text-slate-600">Valor</label><input id="amount" name="amount" data-mask="currency" inputmode="numeric" value="{{ old('amount', number_format($subscription->monthly_amount_cents / 100, 2, ',', '.')) }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><x-input-error :messages="$errors->get('amount')" /></div>
            <div class="flex items-end"><button class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white hover:bg-slate-800">Gerar mensalidade</button></div>
        </form>

        <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Referência</th><th class="px-4 py-3">Vencimento</th><th class="px-4 py-3">Valor</th><th class="px-4 py-3">Situação</th><th class="px-4 py-3">Ação</th></tr></thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($invoices as $invoice)
                        @php($isOverdue = $invoice->paid_at === null && $invoice->due_date->isBefore(today()))
                        <tr>
                            <td class="px-4 py-4 font-bold text-slate-950">{{ $invoice->reference_month->translatedFormat('F \d\e Y') }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ $invoice->due_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-4 font-bold text-slate-950">R$ {{ number_format($invoice->amount_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-4 py-4"><span @class(['rounded-full px-2.5 py-1 text-xs font-bold', 'bg-emerald-100 text-emerald-800' => $invoice->paid_at, 'bg-red-100 text-red-800' => $isOverdue, 'bg-amber-100 text-amber-800' => ! $invoice->paid_at && ! $isOverdue])>{{ $invoice->paid_at ? 'Pago em '.$invoice->paid_at->format('d/m/Y') : ($isOverdue ? 'Vencida' : 'Em aberto') }}</span></td>
                            <td class="px-4 py-4">
                                @if (! $invoice->paid_at)
                                    <div class="flex min-w-56 flex-col items-start gap-3">
                                        @if ($whatsappUrls->get($invoice->id))
                                            <a href="{{ $whatsappUrls->get($invoice->id) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-emerald-600 px-3 py-2 font-bold text-white hover:bg-emerald-700">Cobrar no WhatsApp</a>
                                        @else
                                            <span class="text-xs font-semibold text-slate-500">Cadastre o WhatsApp financeiro para cobrar</span>
                                        @endif
                                        <details><summary class="cursor-pointer font-bold text-emerald-700">Registrar pagamento</summary><form method="POST" action="{{ route('platform.invoices.payments.store', [$company, $invoice]) }}" class="mt-3 grid min-w-56 gap-2">@csrf<input name="paid_on" type="date" value="{{ today()->toDateString() }}" required class="rounded-lg border border-slate-300 px-3 py-2"><select name="payment_method" required class="rounded-lg border border-slate-300 bg-white px-3 py-2">@foreach ($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><label class="flex items-start gap-2 rounded-lg bg-emerald-50 p-2 text-xs font-semibold text-emerald-900"><input type="checkbox" name="reactivate_access" value="1" checked class="mt-0.5 size-4 rounded border-emerald-300 text-emerald-700">Reativar o acesso da empresa com esta baixa</label><button class="rounded-lg bg-emerald-700 px-3 py-2 font-bold text-white">Confirmar</button></form></details>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">{{ $paymentMethods[$invoice->payment_method] ?? ($invoice->payment_method === 'credit_card' ? 'Cartão recorrente · Mercado Pago' : 'Pagamento registrado') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Nenhuma mensalidade gerada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Histórico administrativo</h3>
        <p class="mt-1 text-sm text-slate-500">Alterações feitas pelo acesso master.</p>
        <div class="mt-5 divide-y divide-slate-100">
            @forelse ($company->platformActivityLogs as $activity)
                <div class="py-4 first:pt-0"><div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><p class="font-bold text-slate-950">{{ $activity->description }}</p><time class="text-xs text-slate-500">{{ $activity->created_at->format('d/m/Y H:i') }}</time></div><p class="mt-1 text-xs text-slate-500">Por {{ $activity->actor?->name ?? 'Administrador removido' }}{{ $activity->ip_address ? ' · IP '.$activity->ip_address : '' }}</p></div>
            @empty
                <p class="py-6 text-center text-sm text-slate-500">Nenhuma ação administrativa registrada.</p>
            @endforelse
        </div>
    </section>
@endsection
