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
    <div data-reveal data-reveal-delay="1" class="min-w-0">
        <div class="rounded-3xl border border-brand-200/80 bg-white p-6 shadow-xl shadow-brand-950/5 sm:p-8">
            <div class="flex items-center justify-between gap-3">
                <span class="rounded-full bg-brand-50 px-3.5 py-1 text-xs font-black uppercase tracking-wider text-brand-800">Simulador Interativo</span>
                <span class="text-xs font-semibold text-slate-600">Tempo real</span>
            </div>

            <h3 class="mt-4 text-xl font-bold tracking-tight text-brand-950">Simulador de ponto de equilíbrio</h3>
            <p class="mt-1 text-sm text-slate-600">Ajuste o exemplo à realidade do seu negócio:</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label for="average-ticket" class="text-xs font-bold uppercase tracking-wider text-slate-600">Ticket médio por comanda</label>
                    <div class="relative flex items-center">
                        <span class="pointer-events-none absolute left-4 text-sm font-bold text-slate-600">R$</span>
                        <input id="average-ticket" type="number" min="1" max="1000000" step="0.01" value="{{ $exampleTicket }}" inputmode="decimal" aria-describedby="simulation-note" class="w-full rounded-xl border border-slate-300 bg-slate-50/50 py-3 pr-4 pl-11 text-lg font-black text-slate-950 transition-colors focus:border-brand-600 focus:bg-white focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="missed-tabs" class="text-xs font-bold uppercase tracking-wider text-slate-600">Consumos não cobrados no mês</label>
                    <div class="relative flex items-center">
                        <input id="missed-tabs" type="number" min="0" max="1000000" step="1" value="{{ $exampleMissedTabs }}" inputmode="numeric" aria-describedby="simulation-note" class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-lg font-black text-slate-950 transition-colors focus:border-brand-600 focus:bg-white focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    </div>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2" aria-live="polite" aria-atomic="true">
                <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-600">Exposição mensal estimada</dt>
                    <dd id="monthly-exposure" class="mt-2 text-2xl font-black text-slate-950">R$ {{ number_format($exampleExposure, 0, ',', '.') }}</dd>
                </div>
                <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-600">Equivalente à mensalidade</dt>
                    <dd id="break-even-tabs" class="mt-2 text-2xl font-black text-slate-950">{{ $exampleBreakEven }} {{ $exampleBreakEven === 1 ? 'comanda' : 'comandas' }}</dd>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-5 sm:col-span-2">
                    <dt class="text-xs font-black uppercase tracking-wider text-emerald-800">Diferença em relação à assinatura</dt>
                    <dd id="estimated-balance" class="mt-1 text-3xl font-black text-emerald-950">R$ {{ number_format(abs($exampleBalance), 0, ',', '.') }}</dd>
                    <dd id="balance-description" class="mt-2 text-xs font-medium leading-5 text-emerald-900">{{ $exampleBalance >= 0 ? 'Se esses consumos fossem cobrados, esse seria o valor restante após a mensalidade.' : 'Esse é o valor que falta para os consumos informados equivalerem à mensalidade.' }}</dd>
                </div>
            </dl>

            <p id="simulation-note" class="mt-5 text-center text-xs leading-5 text-slate-600">Simulação educativa, sem promessa de economia ou faturamento. Os valores dependem dos dados informados e da sua operação.</p>
        </div>
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
