@php
    $exampleTicket = 35;
    $exampleMissedTabs = 20;
    $exampleExposure = $exampleTicket * $exampleMissedTabs;
    $exampleBreakEven = (int) ceil($monthlyAmount / $exampleTicket);
    $exampleBalance = $exampleExposure - $monthlyAmount;
@endphp
<div class="grid gap-12 lg:grid-cols-2 lg:gap-24">
    <div data-reveal class="flex flex-col items-start gap-5">
        <p class="landing-kicker">O que muda na prática</p>
        <h2 id="results-title" class="landing-heading">Veja o valor<br>do controle.</h2>
        <p class="landing-copy max-w-lg">Consumo identificado, fechamento conferível e fiado com histórico. O ganho começa quando a informação deixa de se perder pelo caminho.</p>
        <p class="max-w-lg text-base leading-7 text-slate-600">Use seus próprios números para comparar o valor que pode ficar sem cobrança com o custo da assinatura.</p>
        <a href="{{ route('trial.create') }}" class="landing-link mt-2 text-brand-700">Testar na minha operação <span aria-hidden="true">→</span></a>
    </div>
    <div data-reveal data-reveal-delay="1" class="min-w-0 border-t border-brand-200 pt-7">
        <h3 class="text-lg font-semibold tracking-tight text-brand-950">Simulador de ponto de equilíbrio</h3>
        <p class="mt-2 text-base leading-7 text-slate-600">Ajuste o exemplo à realidade do seu negócio.</p>
        <div class="mt-7 grid gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-2.5">
                <label for="average-ticket" class="text-sm font-semibold leading-6 text-slate-700">Ticket médio por comanda (R$)</label>
                <input id="average-ticket" type="number" min="1" max="1000000" step="0.01" value="{{ $exampleTicket }}" inputmode="decimal" aria-describedby="simulation-note" class="min-h-14 w-full rounded-lg border border-stone-300 bg-white px-4 py-3 text-lg font-semibold text-brand-950">
            </div>
            <div class="flex flex-col gap-2.5">
                <label for="missed-tabs" class="text-sm font-semibold leading-6 text-slate-700">Consumos não cobrados no mês</label>
                <input id="missed-tabs" type="number" min="0" max="1000000" step="1" value="{{ $exampleMissedTabs }}" inputmode="numeric" aria-describedby="simulation-note" class="min-h-14 w-full rounded-lg border border-stone-300 bg-white px-4 py-3 text-lg font-semibold text-brand-950">
            </div>
        </div>
        <dl class="mt-8 grid gap-6 border-y border-stone-200 py-7 sm:grid-cols-2" aria-live="polite" aria-atomic="true">
            <div class="flex flex-col gap-3">
                <dt class="text-sm leading-6 text-slate-600">Exposição mensal estimada</dt>
                <dd id="monthly-exposure" class="landing-metric">R$ {{ number_format($exampleExposure, 0, ',', '.') }}</dd>
            </div>
            <div class="flex flex-col gap-3">
                <dt class="text-sm leading-6 text-slate-600">Equivalente à mensalidade</dt>
                <dd id="break-even-tabs" class="landing-metric">{{ $exampleBreakEven }} {{ $exampleBreakEven === 1 ? 'comanda' : 'comandas' }}</dd>
            </div>
            <div class="flex flex-col gap-3 sm:col-span-2">
                <dt class="text-sm leading-6 text-slate-600">Diferença em relação à assinatura</dt>
                <dd id="estimated-balance" class="landing-metric">R$ {{ number_format(abs($exampleBalance), 0, ',', '.') }}</dd>
                <dd id="balance-description" class="max-w-md text-base leading-7 text-slate-600">{{ $exampleBalance >= 0 ? 'Se esses consumos fossem cobrados, esse seria o valor restante após a mensalidade.' : 'Esse é o valor que falta para os consumos informados equivalerem à mensalidade.' }}</dd>
            </div>
        </dl>
        <p id="simulation-note" class="mt-5 text-sm leading-6 text-slate-600">Simulação educativa, sem promessa de economia ou faturamento. Os valores dependem dos dados informados e da sua operação.</p>
        <noscript><p class="mt-3 text-sm leading-6 text-slate-600">Exemplo: R$ {{ $exampleTicket }} por comanda e {{ $exampleMissedTabs }} consumos por mês. Ative o JavaScript para simular outros valores.</p></noscript>
    </div>
</div>

<script>
    (() => {
        const monthlyPrice = {{ Js::from($monthlyAmount) }};
        const ticketInput = document.getElementById('average-ticket');
        const missedInput = document.getElementById('missed-tabs');
        const exposureOutput = document.getElementById('monthly-exposure');
        const breakEvenOutput = document.getElementById('break-even-tabs');
        const balanceOutput = document.getElementById('estimated-balance');
        const balanceDescription = document.getElementById('balance-description');
        const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });

        const updateSimulation = () => {
            const ticket = Math.min(Math.max(Number(ticketInput.value) || 0, 1), 1000000);
            const missed = Math.min(Math.max(Math.floor(Number(missedInput.value) || 0), 0), 1000000);
            const exposure = ticket * missed;
            const breakEven = Math.ceil(monthlyPrice / ticket);
            const balance = exposure - monthlyPrice;

            exposureOutput.textContent = currency.format(exposure);
            breakEvenOutput.textContent = `${breakEven} ${breakEven === 1 ? 'comanda' : 'comandas'}`;
            balanceOutput.textContent = currency.format(Math.abs(balance));
            balanceDescription.textContent = balance >= 0
                ? 'Se esses consumos fossem cobrados, esse seria o valor restante após a mensalidade.'
                : 'Esse é o valor que falta para os consumos informados equivalerem à mensalidade.';
        };

        ticketInput.addEventListener('input', updateSimulation);
        missedInput.addEventListener('input', updateSimulation);
        updateSimulation();
    })();
</script>
