@php
    $trialDays = (int) config('giracomanda.subscription.trial_days', 7);
    $monthlyAmount = (int) config('giracomanda.subscription.monthly_amount_cents', 19700) / 100;
    $monthlyAmountFormatted = number_format($monthlyAmount, 0, ',', '.');
    $yearlyAmount = (int) config('giracomanda.subscription.yearly_amount_cents', 197000) / 100;
    $yearlyAmountFormatted = number_format($yearlyAmount, 0, ',', '.');
    $yearlyEquivalentMonthly = number_format($yearlyAmount / 12, 2, ',', '.');
    $supportWhatsapp = preg_replace('/\D+/', '', (string) config('giracomanda.subscription.support_whatsapp'));
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="motion-safe:scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Do QR Code ao recebimento: organize comandas, consumo por pessoa, rateio, caixa e fiado com o GiraComanda. Teste {{ $trialDays }} dias grátis. Planos mensal de R$ {{ $monthlyAmountFormatted }} e anual com 2 meses grátis.">
    <title>GiraComanda · Cada consumo na conta certa</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="marketing-page">
    <a href="#conteudo" class="sr-only fixed top-3 left-3 z-[60] rounded-lg bg-white px-5 py-3 font-semibold text-brand-950 shadow-lg focus:not-sr-only">Pular para o conteúdo</a>

    <header class="landing-header sticky top-0 z-50 border-b backdrop-blur-md">
        <div class="landing-container flex min-h-20 items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 text-lg font-bold tracking-tight text-brand-950 sm:text-xl" aria-label="Página inicial do GiraComanda">
                <span class="grid size-8 place-items-center rounded-lg bg-brand-950 text-base font-bold text-white sm:size-9" aria-hidden="true">G</span>
                GiraComanda
            </a>
            <nav class="hidden items-center gap-7 text-base font-medium text-slate-600 xl:flex" aria-label="Navegação principal">
                <a href="#solucao" class="py-3 hover:text-brand-950">Como funciona</a>
                <a href="#recursos" class="py-3 hover:text-brand-950">Recursos</a>
                <a href="#plano" class="py-3 hover:text-brand-950">Plano</a>
                <a href="#duvidas" class="py-3 hover:text-brand-950">Dúvidas</a>
            </nav>
            <div class="flex shrink-0 items-center gap-5">
                <a href="{{ route('login') }}" class="landing-link text-sm text-brand-950 sm:text-base">Já sou cliente <span class="hidden sm:inline" aria-hidden="true">↗</span></a>
                <a href="{{ route('trial.create') }}" class="landing-cta hidden min-h-11 px-5 py-2.5 sm:inline-flex">Testar grátis</a>
            </div>
        </div>
    </header>

    <main id="conteudo">
        <section class="landing-hero landing-dark text-white" aria-labelledby="hero-title">
            <div class="landing-container grid items-center gap-12 py-14 sm:gap-16 sm:py-16 lg:grid-cols-[.9fr_1.1fr] lg:gap-12">
                <div class="flex flex-col items-start gap-6">
                    <p data-hero-enter="0" class="max-w-sm text-sm leading-6 font-medium tracking-[0.08em] text-brand-200 uppercase">Gestão para restaurantes, lanchonetes e cantinas</p>
                    <h1 id="hero-title" data-hero-enter="1" class="text-[clamp(2rem,10.5vw,2.6rem)] leading-[1.06] font-semibold tracking-[-0.045em] sm:text-6xl lg:text-[clamp(3.1rem,4.6vw,4.25rem)]">Cada consumo.<br>Na conta certa.</h1>
                    <p data-hero-enter="2" class="max-w-lg text-lg leading-8 text-brand-100 sm:text-xl sm:leading-9">Do cartão com QR Code ao fechamento do caixa: organize o consumo por pessoa, divida a conta e acompanhe cada pagamento, inclusive o fiado.</p>
                    <div data-hero-enter="3" class="flex w-full flex-col items-start gap-3 pt-1">
                        <a href="{{ route('trial.create') }}" class="landing-cta w-full sm:w-auto">Começar {{ $trialDays }} dias grátis <span aria-hidden="true">→</span></a>
                        <p class="text-sm leading-6 text-brand-200">Sem cartão de crédito. Depois, R$ {{ $monthlyAmountFormatted }}/mês ou plano anual com 2 meses grátis.</p>
                    </div>
                    <a href="#solucao" class="landing-link text-brand-100">Conhecer a solução <span aria-hidden="true">↓</span></a>
                </div>

                @include('marketing.partials.product-preview')
            </div>
        </section>

        <section class="landing-section landing-pain" aria-labelledby="pain-title">
            <div class="landing-container grid gap-12 lg:grid-cols-[.85fr_1.15fr] lg:gap-24">
                <div data-reveal class="flex flex-col items-start gap-5">
                    <p class="landing-kicker">O custo do improviso</p>
                    <h2 id="pain-title" class="landing-heading">O improviso<br>custa no caixa.</h2>
                    <p class="landing-copy max-w-md">Papel, planilha e memória deixam lacunas. E, no fim do turno, é você quem precisa descobrir de onde veio a diferença.</p>
                </div>
                <ol class="divide-y divide-stone-200 border-y border-stone-200">
                    @foreach ([['01', 'Consumo que fica sem dono', 'O produto foi servido, mas falta saber em qual conta ele deveria entrar.'], ['02', 'Divisão que trava o caixa', 'O rateio fica para a última hora, sem um registro claro do que cada pessoa consumiu.'], ['03', 'Fiado difícil de cobrar', 'Um valor solto no caderno não explica os itens, os pagamentos parciais nem o vencimento.']] as [$number, $title, $description])
                        <li data-reveal data-reveal-delay="{{ $loop->index }}" class="flex gap-5 py-7 sm:gap-7 sm:py-8">
                            <span class="landing-number w-12 shrink-0" aria-hidden="true">{{ $number }}</span>
                            <div class="flex flex-col gap-3">
                                <h3 class="text-xl leading-snug font-semibold tracking-tight text-brand-950">{{ $title }}</h3>
                                <p class="text-base leading-7 text-slate-600 sm:text-lg">{{ $description }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="solucao" class="landing-section landing-flow landing-dark scroll-mt-20 text-white" aria-labelledby="solution-title">
            <div class="landing-container">
                <div data-reveal class="grid gap-6 lg:grid-cols-2 lg:items-end lg:gap-24">
                    <div class="flex flex-col gap-5">
                        <p class="landing-kicker text-brand-200">Da entrada ao recebimento</p>
                        <h2 id="solution-title" class="landing-heading text-white">Um fluxo.<br>Contas claras.</h2>
                    </div>
                    <p class="landing-copy max-w-lg text-brand-100">A informação entra no atendimento e acompanha a operação até o caixa e o contas a receber. Cada etapa continua de onde a anterior parou.</p>
                </div>
                <ol class="mt-12 grid gap-x-10 sm:mt-16 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([['01', 'Identifique', 'Entregue o cartão QR reutilizável, personalizado com a marca do seu estabelecimento.'], ['02', 'Registre', 'Leia pela câmera do tablet e lance produtos por unidade ou peso na comanda.'], ['03', 'Divida', 'Associe o consumo às pessoas e divida o mesmo produto sem perder o histórico.'], ['04', 'Receba', 'Registre dinheiro, PIX ou cartão no caixa. Se ficar para depois, gere uma conta a receber.']] as [$number, $title, $description])
                        <li data-reveal data-reveal-delay="{{ $loop->index }}" class="landing-step flex flex-col items-start gap-4 border-t border-white/20 py-8">
                            <span class="landing-number mb-3 text-brand-300" aria-hidden="true">{{ $number }}</span>
                            <h3 class="text-2xl font-semibold tracking-tight text-white">{{ $title }}</h3>
                            <p class="text-base leading-7 text-brand-100">{{ $description }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="resultados" class="landing-section landing-results scroll-mt-20" aria-labelledby="results-title">
            <div class="landing-container">
                @include('marketing.partials.value-simulator')
            </div>
        </section>

        <section id="recursos" class="landing-section landing-features scroll-mt-20" aria-labelledby="features-title">
            <div class="landing-container">
                <div data-reveal class="grid gap-6 lg:grid-cols-2 lg:items-end lg:gap-24">
                    <div class="flex flex-col gap-5">
                        <p class="landing-kicker">Não é só mais um PDV</p>
                        <h2 id="features-title" class="landing-heading">Sua operação,<br>em sintonia.</h2>
                    </div>
                    <p class="landing-copy max-w-lg">QR reutilizável, consumo por pessoa, rateio e fiado detalhado trabalham juntos. Do balcão à gestão, cada registro tem contexto.</p>
                </div>
                <div class="mt-12 divide-y divide-stone-300 border-y border-stone-300 sm:mt-16">
                    @foreach ([
                        ['Atendimento que flui', 'Para servir, registrar e dividir com clareza.', ['Cartões QR personalizados e leitura pela câmera.', 'Produtos por unidade ou peso e rateio por pessoa.', 'Clientes cadastrados e histórico de consumo.']],
                        ['Financeiro com contexto', 'Para saber o que entrou e o que falta receber.', ['Caixa com abertura, sangria, suprimento e fechamento.', 'Contas a receber, vencimentos e pagamentos parciais.', 'Resumo mensal para impressão e envio assistido pelo WhatsApp.']],
                        ['Gestão que acompanha', 'Para enxergar a operação além do balcão.', ['Produtos, estoque mínimo e movimentações.', 'Relatórios, auditoria, usuários e permissões.', 'Filiais com dados isolados e compartilhamento opcional de clientes e produtos.']],
                    ] as [$title, $description, $features])
                        <article data-reveal class="landing-feature-row grid gap-6 py-9 md:grid-cols-[.85fr_1.15fr] md:gap-16 lg:grid-cols-2 lg:gap-24">
                            <div class="flex flex-col gap-3">
                                <h3 class="text-2xl font-semibold tracking-tight text-brand-950">{{ $title }}</h3>
                                <p class="text-base leading-7 text-slate-600">{{ $description }}</p>
                            </div>
                            <ul class="flex flex-col gap-4 text-base leading-7 text-slate-700">
                                @foreach ($features as $feature)
                                    <li class="flex gap-3"><span class="font-semibold text-brand-700" aria-hidden="true">✓</span><span>{{ $feature }}</span></li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
                <p class="mt-8 text-base leading-7 text-slate-600">Para restaurantes, lanchonetes, cantinas e clubes que precisam organizar o consumo recorrente.</p>
            </div>
        </section>

        <section id="plano" class="landing-section landing-pricing landing-dark scroll-mt-20 text-white" aria-labelledby="pricing-title">
            <div data-reveal class="landing-container flex flex-col items-center text-center">
                <p class="text-sm font-semibold tracking-[0.12em] text-brand-200 uppercase">Planos transparentes. A operação completa.</p>
                <h2 id="pricing-title" class="landing-heading mt-5 max-w-2xl text-white">Tudo para sua operação.</h2>
                <p class="mt-5 max-w-xl text-lg leading-8 text-brand-100">Experimente com seus produtos, sua equipe e sua rotina antes de assinar.</p>

                <div class="mt-10 grid w-full max-w-4xl gap-6 sm:mt-12 md:grid-cols-2 text-left">
                    <!-- Plano Mensal -->
                    <div class="flex flex-col justify-between rounded-3xl border border-white/10 bg-white/5 p-8 backdrop-blur-sm">
                        <div>
                            <span class="rounded-full bg-white/10 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-brand-200">Plano Mensal</span>
                            <div class="mt-5 flex items-baseline gap-2">
                                <span class="text-3xl font-semibold">R$</span>
                                <span class="text-5xl font-black">{{ $monthlyAmountFormatted }}</span>
                                <span class="text-brand-200">/mês</span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-brand-200">Ideal para quem busca máxima flexibilidade, com cobrança mensal automática no cartão.</p>
                        </div>
                        <div class="mt-8">
                            <a href="{{ route('trial.create') }}" class="landing-cta w-full text-center">Começar teste grátis</a>
                            <p class="mt-3 text-center text-xs text-brand-300">{{ $trialDays }} dias grátis, sem cartão para começar</p>
                        </div>
                    </div>

                    <!-- Plano Anual -->
                    <div class="relative flex flex-col justify-between rounded-3xl border-2 border-warm-500 bg-white/10 p-8 shadow-2xl backdrop-blur-sm">
                        <span class="absolute -top-3 right-6 rounded-full bg-warm-500 px-3.5 py-1 text-xs font-black uppercase tracking-wider text-brand-950">2 meses grátis</span>
                        <div>
                            <span class="rounded-full bg-warm-500/20 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-warm-300">Plano Anual</span>
                            <div class="mt-5 flex items-baseline gap-2">
                                <span class="text-3xl font-semibold">R$</span>
                                <span class="text-5xl font-black">{{ $yearlyAmountFormatted }}</span>
                                <span class="text-brand-200">/ano</span>
                            </div>
                            <p class="mt-1 text-sm font-bold text-warm-300">Equivalente a R$ {{ $yearlyEquivalentMonthly }}/mês</p>
                            <p class="mt-2 text-sm leading-6 text-brand-200">Economize 2 mensalidades inteiras pagando 10 meses para usar o ano todo.</p>
                        </div>
                        <div class="mt-8">
                            <a href="{{ route('trial.create') }}" class="landing-cta w-full bg-warm-500 text-center font-black text-brand-950 hover:bg-warm-400">Começar com plano anual</a>
                            <p class="mt-3 text-center text-xs text-brand-300">{{ $trialDays }} dias grátis para testar à vontade</p>
                        </div>
                    </div>
                </div>

                <ul class="mt-12 flex max-w-3xl flex-wrap justify-center gap-x-8 gap-y-4 border-t border-white/20 pt-8 text-base text-brand-100">
                    @foreach (['Comandas e rateio', 'Caixa e contas a receber', 'Estoque e relatórios', 'Usuários e filiais'] as $included)
                        <li class="flex items-center gap-2"><span class="text-brand-300" aria-hidden="true">✓</span>{{ $included }}</li>
                    @endforeach
                </ul>
                <p class="mt-8 max-w-xl text-base leading-7 text-brand-200">Ao terminar o teste, você escolhe se prefere o plano mensal ou anual com desconto na área de assinatura.</p>
                <a href="{{ route('login') }}" class="landing-link mt-5 text-white">Já sou cliente <span aria-hidden="true">↗</span></a>
            </div>
        </section>

        <section id="duvidas" class="landing-section landing-faq scroll-mt-20" aria-labelledby="faq-title">
            <div class="landing-container grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:gap-24">
                <div data-reveal class="flex flex-col items-start gap-5">
                    <p class="landing-kicker">Perguntas frequentes</p>
                    <h2 id="faq-title" class="landing-heading">Pode perguntar.</h2>
                    <p class="landing-copy max-w-sm">Entenda os próximos passos e comece o teste com clareza.</p>
                    @if ($supportWhatsapp)
                        <a href="https://wa.me/{{ $supportWhatsapp }}?text={{ urlencode('Olá! Quero conhecer melhor o GiraComanda.') }}" target="_blank" rel="noopener" class="landing-link mt-2 text-brand-700">Falar sobre o GiraComanda <span aria-hidden="true">↗</span></a>
                    @endif
                </div>
                <div data-reveal class="divide-y divide-brand-200 border-y border-brand-200">
                    @foreach ([['Preciso de cartão para começar?', 'Não. Você pode criar sua conta e testar por '. $trialDays .' dias sem informar cartão de crédito. A primeira mensalidade só fica disponível depois do teste.'], ['E quando os '. $trialDays .' dias terminam?', 'O acesso operacional é interrompido e uma mensalidade de R$ '. $monthlyAmountFormatted .' é gerada. Você pode regularizar o pagamento na área de assinatura. Após a confirmação, continua usando a mesma conta, com os dados preservados.'], ['Posso usar no tablet?', 'Sim. O GiraComanda é online e pode ser usado no tablet, inclusive para ler o QR Code pela câmera do aparelho. É necessário acesso à internet.'], ['Como funcionam o fiado e o rateio?', 'Você pode dividir o mesmo item entre pessoas. Se o pagamento ficar para depois, a conta a receber mantém os produtos consumidos, o vencimento, os pagamentos parciais e o saldo pendente.'], ['Posso gerenciar várias filiais?', 'Sim. Empresas podem ter várias filiais, com dados isolados e compartilhamento opcional de clientes e produtos entre unidades.'], ['O sistema emite nota fiscal?', 'Os comprovantes do GiraComanda são não fiscais. O sistema não emite nota fiscal.']] as [$question, $answer])
                        <details class="group py-6">
                            <summary class="flex min-h-7 cursor-pointer list-none items-center justify-between gap-5 text-lg leading-7 font-semibold text-brand-950">
                                {{ $question }}
                                <span class="shrink-0 text-2xl font-normal text-brand-700 group-open:rotate-45 motion-safe:transition-transform" aria-hidden="true">+</span>
                            </summary>
                            <p class="max-w-2xl pt-4 pr-6 text-base leading-7 text-slate-600 sm:text-lg">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-dark border-t border-white/10 bg-brand-950 py-10">
        <div class="landing-container flex flex-col gap-8 md:flex-row md:items-end md:justify-between">
            <div class="flex flex-col gap-3">
                <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight text-white">GiraComanda</a>
                <p class="text-base leading-7 text-brand-100">Do consumo ao recebimento, tudo no mesmo fluxo.</p>
                <p class="text-sm text-brand-200">© {{ now()->year }} GiraComanda · Danilo Cardoso</p>
            </div>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-brand-100" aria-label="Links do rodapé">
                <a href="{{ route('privacy') }}" class="landing-link text-sm hover:text-white">Privacidade</a>
                <a href="{{ route('terms') }}" class="landing-link text-sm hover:text-white">Termos de uso</a>
                <a href="{{ route('login') }}" class="landing-link text-sm hover:text-white">Acessar sistema</a>
            </nav>
        </div>
    </footer>
</body>
</html>
