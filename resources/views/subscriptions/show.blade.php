<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assinatura · {{ config('app.name') }}</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-950 text-white">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center px-5 py-12 sm:px-8">
        <div class="grid w-full overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-2xl lg:grid-cols-[1fr_0.8fr]">
            <section class="p-7 sm:p-10 lg:p-14">
                <div class="flex items-center gap-3">
                    <div class="grid size-11 place-items-center rounded-2xl bg-warm-500 text-lg font-black text-brand-950">G</div>
                    <span class="text-xl font-bold">GiraComanda</span>
                </div>

                <p class="mt-12 text-sm font-bold uppercase tracking-[0.2em] text-warm-400">Assinatura da {{ $company->name }}</p>
                @if ($subscription?->isTrialExpired())
                    <h1 class="mt-4 max-w-xl text-4xl font-bold leading-tight sm:text-5xl">Seu período gratuito terminou.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-brand-100">Seus dados continuam preservados. Escolha uma forma de pagamento para continuar usando a operação.</p>
                @elseif ($subscription?->allowsAccess())
                    <h1 class="mt-4 max-w-xl text-4xl font-bold leading-tight sm:text-5xl">Sua assinatura está liberada.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-brand-100">Você pode voltar ao sistema ou consultar as condições do plano.</p>
                @else
                    <h1 class="mt-4 max-w-xl text-4xl font-bold leading-tight sm:text-5xl">Seu acesso precisa de atenção.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-brand-100">Regularize a mensalidade ou fale com o atendimento para revisar sua situação.</p>
                @endif

                <div class="mt-9 flex flex-wrap gap-3">
                    @if ($subscription?->allowsAccess())
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-warm-500 px-6 py-3.5 font-black text-brand-950 hover:bg-warm-400">Voltar ao sistema</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl border border-white/20 px-6 py-3.5 font-bold text-white hover:bg-white/10">Sair</button></form>
                </div>
            </section>

            <aside class="bg-white p-7 text-slate-900 sm:p-10 lg:p-12">
                <x-flash-message />
                @if ($errors->any())<div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                <p class="text-sm font-black uppercase tracking-[0.18em] text-brand-700">Plano GiraComanda</p>
                <div class="mt-4 flex items-end gap-2"><span class="text-5xl font-black">R$ {{ number_format(($subscription?->monthly_amount_cents ?? config('giracomanda.subscription.monthly_amount_cents')) / 100, 0, ',', '.') }}</span><span class="pb-1 text-slate-500">/mês</span></div>
                <p class="mt-4 leading-7 text-slate-600">Comandas por QR Code, rateio, fiado detalhado, caixa, estoque, relatórios e gestão de filiais.</p>

                <div class="mt-8 grid gap-3">
                    @if ($subscription?->paid_until)
                        <p class="text-sm font-semibold text-brand-800">Período pago até {{ $subscription->paid_until->format('d/m/Y H:i') }}.</p>
                    @endif
                    @if ($onlinePaymentsEnabled && $paymentEnvironment === 'test')
                        <p class="rounded-xl bg-amber-50 p-4 text-sm font-bold text-amber-900">Ambiente de teste. Use somente contas e cartões de teste do Mercado Pago. Pagamentos simulados não quitam mensalidades nem liberam acesso real.</p>
                    @endif
                    @if ($canManageBilling && $onlinePaymentsEnabled && $openInvoice && ! $recurrence && ! $subscription?->isTrialActive())
                        <form method="POST" action="{{ route('subscription.checkouts.store') }}" class="grid gap-4 rounded-2xl border border-brand-200 bg-brand-50 p-5">
                            @csrf
                            <p class="font-black text-brand-950">Escolha como continuar</p>
                            <div><label for="payer_email" class="text-sm font-bold text-slate-700">E-mail do pagador no Mercado Pago</label><input id="payer_email" name="payer_email" type="email" required maxlength="254" value="{{ old('payer_email', $paymentEnvironment === 'test' ? '' : auth()->user()->email) }}" aria-describedby="payer-email-help" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-950"><p id="payer-email-help" class="mt-2 text-sm leading-6 text-slate-600">{{ $paymentEnvironment === 'test' ? 'Informe o e-mail da conta COMPRADORA de teste do Mercado Pago, não o login do GiraComanda. Comprador e vendedor precisam pertencer a ambientes compatíveis.' : 'Informe o e-mail da conta que autorizará o pagamento no Mercado Pago.' }}</p></div>
                            <button name="kind" value="pix" class="rounded-xl bg-brand-800 px-5 py-3.5 font-bold text-white hover:bg-brand-900">Gerar Pix desta mensalidade</button>
                            <label class="flex items-start gap-2 text-sm leading-6 text-slate-700"><input type="checkbox" name="recurring_consent" value="1" class="mt-1 size-4 shrink-0 accent-brand-700">Autorizo a assinatura mensal recorrente de R$ {{ number_format($subscription->monthly_amount_cents / 100, 2, ',', '.') }}, até o cancelamento. A confirmação do cartão acontece no Mercado Pago.</label>
                            <button name="kind" value="card" class="rounded-xl bg-warm-500 px-5 py-3.5 font-black text-brand-950 hover:bg-warm-400">Assinar com cartão</button>
                            <p class="text-xs leading-5 text-slate-600">O Pix não tem renovação automática. Para trocar do cartão para Pix, cancele primeiro a recorrência. O retorno do checkout não confirma pagamento.</p>
                        </form>
                    @endif
                    @if ($canManageBilling)
                        @if ($failedCheckout)
                            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm leading-6 text-red-900"><p class="font-bold">{{ $failedCheckout->statusLabel() }}{{ $failedCheckout->failure_http_status ? ' · HTTP '.$failedCheckout->failure_http_status : '' }}</p><p class="mt-2">{{ $failedCheckout->failure_message }}</p><p class="mt-2">{{ $failedCheckout->status === 'rejected' ? 'Esta tentativa não confirma assinatura nem pagamento. Corrija o motivo indicado e tente novamente no formulário.' : 'O resultado da criação ainda é desconhecido. Uma consulta sem resultado não confirma pagamento nem cancelamento.' }}</p></div>
                        @endif
                        @foreach ($checkouts->filter(fn ($checkout) => $checkout->isRecurring() || ($checkout->kind === 'pix' && in_array($checkout->status, ['creating', 'pending', 'in_process'], true))) as $checkout)
                            <div class="grid gap-3 rounded-2xl border border-slate-200 p-5">
                                <p class="font-bold">{{ $checkout->kind === 'card' ? 'Cartão recorrente' : 'Pix' }} · {{ $checkout->environment === 'test' ? 'Teste' : 'Produção' }}</p>
                                <p class="text-sm text-slate-600">Situação: {{ $checkout->statusLabel() }}</p>
                                @if (! $checkout->remote_id)<p class="text-sm leading-6 text-amber-900">Não há um ID de cobrança ou assinatura confirmado para esta tentativa. Encerrar a tentativa abaixo é uma ação local, não um cancelamento confirmado no Mercado Pago.</p>@endif
                                @if ($checkout->kind === 'card' && $checkout->status === 'pending' && $checkout->checkout_url)<a href="{{ $checkout->checkout_url }}" class="font-bold text-brand-800 underline">Continuar no Mercado Pago</a>@endif
                                <form method="POST" action="{{ route('subscription.checkouts.update', $checkout) }}">@csrf @method('PUT')<button class="min-h-11 text-sm font-bold text-brand-800 underline">Consultar no Mercado Pago</button></form>
                                <form method="POST" action="{{ route('subscription.checkouts.destroy', $checkout) }}">@csrf @method('DELETE')<button class="min-h-11 text-sm font-bold text-red-700 underline">{{ ! $checkout->remote_id ? 'Encerrar tentativa local' : ($checkout->kind === 'card' ? 'Cancelar próximas cobranças' : 'Cancelar este Pix') }}</button></form>
                                @if ($checkout->expires_at)<p class="text-xs text-slate-500">QR Code válido até {{ $checkout->expires_at->format('d/m/Y H:i') }}. Após expirar, consulte e cancele antes de gerar outro.</p>@endif
                            </div>
                        @endforeach
                    @endif
                    @if ($openInvoice)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Mensalidade em aberto</p>
                            <p class="mt-2 text-3xl font-black text-slate-950">R$ {{ number_format($openInvoice->amount_cents / 100, 2, ',', '.') }}</p>
                            <p class="mt-1 text-sm text-slate-600">Vencimento em {{ $openInvoice->due_date->format('d/m/Y') }}</p>
                        </div>
                    @endif

                    @if ($openInvoice && ($pixKey || $pixCopyPaste))
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <p class="font-black text-emerald-900">Pague com PIX</p>
                            @if ($pixQrCodeDataUri)
                                <img src="{{ $pixQrCodeDataUri }}" alt="QR Code PIX da mensalidade do GiraComanda" class="mx-auto mt-4 size-52 rounded-2xl bg-white p-3 shadow-sm">
                                <p class="mt-3 text-center text-xs font-bold text-emerald-800">Escaneie o QR Code pelo aplicativo do seu banco.</p>
                            @endif
                            @if ($pixKey)
                                <p class="mt-4 break-all text-sm text-emerald-800"><span class="font-bold">Chave:</span> {{ $pixKey }}</p>
                            @endif
                            @if ($pixCopyPaste)
                                <button type="button" data-copy-text="{{ $pixCopyPaste }}" class="mt-4 w-full rounded-xl bg-emerald-800 px-4 py-3 text-sm font-black text-white hover:bg-emerald-900">Copiar PIX copia e cola</button>
                                <p data-copy-feedback class="mt-2 hidden text-center text-xs font-bold text-emerald-800">Código PIX copiado.</p>
                            @endif
                            <p class="mt-4 text-sm leading-6 text-emerald-800">{{ $dynamicPix ? 'A confirmação depende do Mercado Pago. Após pagar, clique em “Consultar no Mercado Pago”.' : 'Depois do pagamento, envie o comprovante para a confirmação e liberação do acesso.' }}</p>
                        </div>
                    @endif

                    @if (! $openInvoice && $subscription?->allowsAccess())
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950">
                            <p class="font-black">Nenhuma mensalidade pendente</p>
                            <p class="mt-2 text-sm leading-6">Seu acesso está liberado e não há cobrança em aberto neste momento.</p>
                        </div>
                    @endif

                    @if ($openInvoice && ! $pixKey && ! $pixCopyPaste && ! $onlinePaymentsEnabled && ! $recurrence)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
                            <p class="font-black">Pagamento em configuração</p>
                            <p class="mt-2 text-sm leading-6">Fale com o atendimento para receber o PIX de R$ {{ number_format($openInvoice->amount_cents / 100, 2, ',', '.') }} e ter o acesso liberado.</p>
                        </div>
                    @endif

                    @if ($supportWhatsappUrl)
                        <a href="{{ $supportWhatsappUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-2xl border border-slate-300 px-5 py-4 text-center font-bold text-slate-800 hover:bg-slate-50">Falar com o atendimento</a>
                    @endif
                </div>

                <p class="mt-6 text-xs leading-5 text-slate-500">Você mantém acesso ao login e aos canais de pagamento mesmo quando a operação está bloqueada.</p>
            </aside>
        </div>
    </main>
</body>
</html>
