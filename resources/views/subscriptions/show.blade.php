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
        <div class="grid w-full overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-2xl lg:grid-cols-[1fr_0.9fr]">
            <section class="p-7 sm:p-10 lg:p-14">
                <div class="flex items-center gap-3">
                    <div class="grid size-11 place-items-center rounded-2xl bg-warm-500 text-lg font-black text-brand-950">G</div>
                    <span class="text-xl font-bold">GiraComanda</span>
                </div>

                <p class="mt-12 text-sm font-bold uppercase tracking-[0.2em] text-warm-400">Assinatura da {{ $company->name }}</p>
                @if ($subscription?->isTrialExpired())
                    <h1 class="mt-4 max-w-xl text-4xl font-bold leading-tight sm:text-5xl">Seu período gratuito terminou.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-brand-100">Seus dados continuam preservados. Escolha um plano para continuar usando o sistema sem interrupções.</p>
                @elseif ($subscription?->allowsAccess())
                    <h1 class="mt-4 max-w-xl text-4xl font-bold leading-tight sm:text-5xl">Sua assinatura está liberada.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-brand-100">Você tem acesso completo a todas as funcionalidades do GiraComanda.</p>
                @else
                    <h1 class="mt-4 max-w-xl text-4xl font-bold leading-tight sm:text-5xl">Seu acesso precisa de atenção.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-brand-100">Regularize sua assinatura ou fale com o suporte para revisar sua situação.</p>
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
                <p class="text-sm font-black uppercase tracking-[0.18em] text-brand-700">Planos de Assinatura</p>
                <p class="mt-2 text-sm text-slate-600">Comandas digitais, cardápio QR Code, divisão de contas, fiado detalhado, controle de caixa, estoque e relatórios completos.</p>

                <div class="mt-6 grid gap-4">
                    @if ($subscription?->paid_until)
                        <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm font-semibold text-brand-900">
                            Período contratado em vigor até <strong>{{ $subscription->paid_until->format('d/m/Y H:i') }}</strong>.
                        </div>
                    @endif

                    @if ($onlinePaymentsEnabled && $paymentEnvironment === 'test')
                        <p class="rounded-xl bg-amber-50 p-3 text-xs font-bold text-amber-900">Ambiente de teste ativo. Utilize dados e cartões de teste do Mercado Pago.</p>
                    @endif

                    @if ($canManageBilling && $onlinePaymentsEnabled && ! $recurrence)
                        <form method="POST" action="{{ route('subscription.checkouts.store') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            @csrf
                            <input type="hidden" name="kind" value="card">
                            <p class="font-black text-slate-950">Selecione seu plano</p>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border-2 border-slate-200 bg-white p-4 transition-all hover:border-brand-500 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/30">
                                    <div class="flex items-start justify-between">
                                        <span class="text-xs font-black uppercase tracking-wider text-slate-600">Mensal</span>
                                        <input type="radio" name="billing_cycle" value="monthly" checked class="size-4 text-brand-600 focus:ring-brand-500">
                                    </div>
                                    <div class="mt-3">
                                        <p class="text-2xl font-black text-slate-950">R$ {{ number_format($monthlyAmountCents / 100, 2, ',', '.') }}</p>
                                        <p class="text-xs text-slate-500">por mês</p>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-600">Cobrança recorrente a cada 30 dias</p>
                                </label>

                                <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border-2 border-brand-500 bg-white p-4 shadow-sm transition-all hover:border-brand-600 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/40">
                                    <span class="absolute -top-2.5 right-3 rounded-full bg-emerald-700 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">2 meses grátis</span>
                                    <div class="flex items-start justify-between">
                                        <span class="text-xs font-black uppercase tracking-wider text-brand-900">Anual</span>
                                        <input type="radio" name="billing_cycle" value="yearly" class="size-4 text-brand-600 focus:ring-brand-500">
                                    </div>
                                    <div class="mt-3">
                                        <p class="text-2xl font-black text-slate-950">R$ {{ number_format($yearlyAmountCents / 100, 2, ',', '.') }}</p>
                                        <p class="text-xs font-bold text-emerald-800">~R$ {{ number_format($yearlyAmountCents / 1200, 2, ',', '.') }}/mês</p>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-600">Cobrança única anual recorrente</p>
                                </label>
                            </div>

                            <div>
                                <label for="payer_email" class="text-sm font-bold text-slate-700">E-mail do titular no Mercado Pago</label>
                                <input id="payer_email" name="payer_email" type="email" required maxlength="254" value="{{ old('payer_email', $paymentEnvironment === 'test' ? '' : auth()->user()->email) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-950 focus:border-brand-500 focus:ring-brand-500">
                                <p class="mt-1 text-xs text-slate-500">{{ $paymentEnvironment === 'test' ? 'Conta compradora de teste do Mercado Pago.' : 'E-mail da sua conta Mercado Pago onde o cartão será registrado.' }}</p>
                            </div>

                            <label class="flex items-start gap-2.5 text-xs leading-5 text-slate-700">
                                <input type="checkbox" name="recurring_consent" value="1" required class="mt-0.5 size-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span>Autorizo a assinatura recorrente com cartão de crédito no Mercado Pago conforme o plano escolhido, renovável até o cancelamento.</span>
                            </label>

                            <button type="submit" class="w-full rounded-xl bg-warm-500 px-5 py-3.5 text-center font-black text-brand-950 shadow-md transition-colors hover:bg-warm-400">
                                Assinar com cartão
                            </button>
                        </form>
                    @endif

                    @if ($dynamicPix && ($pixKey || $pixCopyPaste))
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <p class="font-black text-emerald-900">Pix em andamento</p>
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
                            <p class="mt-4 text-sm leading-6 text-emerald-800">A confirmação depende do Mercado Pago. Após pagar, clique em “Consultar no Mercado Pago”.</p>
                        </div>
                    @endif

                    @if ($canManageBilling)
                        @if ($failedCheckout && ! $subscription?->allowsAccess())
                            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm leading-6 text-red-900">
                                <p class="font-bold">{{ $failedCheckout->statusLabel() }}{{ $failedCheckout->failure_http_status ? ' · HTTP '.$failedCheckout->failure_http_status : '' }}</p>
                                <p class="mt-1">{{ $failedCheckout->failure_message }}</p>
                            </div>
                        @endif

                        @foreach ($checkouts->filter(fn ($checkout) => $checkout->isRecurring()) as $checkout)
                            <div class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <div class="flex items-center justify-between">
                                    <p class="font-bold text-slate-950">Cartão recorrente ({{ $checkout->billing_cycle === 'yearly' ? 'Anual' : 'Mensal' }})</p>
                                    <span class="rounded-lg bg-white px-2 py-1 text-xs font-semibold text-slate-700 shadow-sm">{{ $checkout->environment === 'test' ? 'Teste' : 'Produção' }}</span>
                                </div>
                                <p class="text-sm text-slate-600">Situação: <strong class="text-slate-900">{{ $checkout->statusLabel() }}</strong></p>

                                @if ($checkout->status === 'pending' && $checkout->checkout_url)
                                    <a href="{{ $checkout->checkout_url }}" class="rounded-xl bg-brand-800 px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-brand-900">Continuar no Mercado Pago</a>
                                @endif

                                <div class="flex flex-wrap gap-4 text-xs font-bold">
                                    <form method="POST" action="{{ route('subscription.checkouts.update', $checkout) }}">
                                        @csrf @method('PUT')
                                        <button class="text-brand-800 underline hover:text-brand-900">Consultar no Mercado Pago</button>
                                    </form>
                                    <form method="POST" action="{{ route('subscription.checkouts.destroy', $checkout) }}">
                                        @csrf @method('DELETE')
                                        <button class="text-red-700 underline hover:text-red-800">{{ ! $checkout->remote_id ? 'Encerrar tentativa local' : 'Cancelar próximas cobranças' }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    @if ($subscription?->allowsAccess() && ! $openInvoice)
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">
                            <p class="font-black">Tudo em dia!</p>
                            <p class="mt-1 text-xs leading-5">Seu acesso está ativo e regularizado.</p>
                        </div>
                    @endif

                    @if ($supportWhatsappUrl)
                        <a href="{{ $supportWhatsappUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-2xl border border-slate-300 px-5 py-3.5 text-center text-sm font-bold text-slate-800 transition-colors hover:bg-slate-50">Falar com o atendimento</a>
                    @endif
                </div>

                <p class="mt-6 text-xs leading-5 text-slate-500">O cancelamento da recorrência pode ser feito a qualquer momento antes da próxima renovação.</p>
            </aside>
        </div>
    </main>
</body>
</html>
