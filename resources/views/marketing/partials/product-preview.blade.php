<figure data-hero-enter="3" class="min-w-0" aria-label="Prévia ilustrativa da operação no GiraComanda">
    <div class="landing-preview overflow-hidden rounded-2xl border border-white/20 bg-white text-slate-800 shadow-2xl shadow-black/35">
        <div class="flex items-center justify-between gap-3 border-b border-stone-200 px-4 py-4 sm:px-6">
            <div class="flex items-center gap-2.5">
                <span class="grid size-7 place-items-center rounded-md bg-brand-950 text-sm font-bold text-white" aria-hidden="true">G</span>
                <span class="text-sm font-semibold">GiraComanda</span>
            </div>
            <span class="flex items-center gap-2 text-xs font-medium text-brand-800"><span class="size-1.5 rounded-full bg-brand-600" aria-hidden="true"></span>Caixa aberto</span>
        </div>
        <div class="grid sm:grid-cols-[96px_minmax(0,1fr)]">
            <div class="hidden flex-col gap-6 border-r border-stone-200 bg-stone-50 px-3 py-6 text-xs font-medium text-slate-600 sm:flex" aria-hidden="true">
                <span class="border-l-2 border-brand-700 pl-2 font-semibold text-brand-950">Comandas</span>
                <span class="pl-2">Caixa</span>
                <span class="pl-2">A receber</span>
                <span class="pl-2">Estoque</span>
                <span class="pl-2">Relatórios</span>
            </div>
            <div class="min-w-0 p-4 sm:p-6">
                <div class="flex items-end justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <p class="text-xs text-slate-500">Sua operação, organizada</p>
                        <p class="text-xl font-semibold tracking-tight text-brand-950">Comandas</p>
                    </div>
                    <span class="text-xs font-medium text-brand-700">Visão do dia</span>
                </div>
                <dl class="my-6 grid grid-cols-2 divide-x divide-stone-200 sm:grid-cols-3">
                    <div class="pr-2"><dt class="text-xs text-slate-600">Abertas</dt><dd class="mt-1.5 text-xl font-semibold tracking-tight text-brand-950">18</dd></div>
                    <div class="px-3"><dt class="text-xs text-slate-600">Em consumo</dt><dd class="mt-1.5 text-xl font-semibold tracking-tight text-brand-950">R$ 842</dd></div>
                    <div class="hidden pl-3 sm:block"><dt class="text-xs text-slate-600">Finalizadas</dt><dd class="mt-1.5 text-xl font-semibold tracking-tight text-brand-950">32</dd></div>
                </dl>
                <div class="border-y border-stone-200">
                    <div class="grid grid-cols-[1fr_auto] gap-3 bg-stone-50 px-3 py-2.5 text-xs font-medium text-slate-600"><span>Comanda / cliente</span><span>Consumo</span></div>
                    @foreach ([['021', 'Ana Martins', 'R$ 86,50'], ['034', 'Marcos Silva', 'R$ 42,00'], ['008', 'Paulo Santos', 'R$ 119,90']] as [$card, $customer, $value])
                        <div class="flex items-center justify-between gap-3 border-t border-stone-100 px-3 py-3.5">
                            <div class="flex flex-col gap-1"><p class="text-sm font-semibold text-brand-950">Cartão {{ $card }}</p><p class="text-xs text-slate-600">{{ $customer }}</p></div>
                            <p class="text-sm font-semibold tabular-nums text-brand-950">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex items-start gap-2.5 text-xs leading-5 text-brand-800">
                    <span class="font-semibold" aria-hidden="true">✓</span>
                    <p>Consumo identificado. Rateio registrado. Caixa atualizado.</p>
                </div>
            </div>
        </div>
    </div>
    <figcaption class="mt-4 flex min-h-11 flex-wrap items-center justify-center gap-x-4 text-center text-sm leading-6 text-brand-200">
        <span>Prévia ilustrativa com dados de demonstração.</span>
        <button type="button" data-motion-toggle aria-pressed="false" class="landing-motion-toggle" hidden>Pausar animações</button>
    </figcaption>
</figure>
