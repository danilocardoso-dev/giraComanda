@extends('layouts.platform')

@section('title', 'Configurações')

@section('content')
    <div class="flex flex-col gap-7">
        <section class="flex flex-col gap-3">
            <p class="text-sm font-bold uppercase tracking-[0.12em] text-brand-700">Pagamentos da plataforma</p>
            <h2 class="text-3xl font-bold tracking-tight text-slate-950">Mercado Pago</h2>
            <p class="max-w-3xl text-base leading-7 text-slate-600">Cadastre as credenciais da conta que receberá as assinaturas do GiraComanda. Esta configuração é exclusiva da administração do SaaS.</p>
        </section>

        <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5 text-sm leading-6 text-brand-900">
            <p class="font-bold">Chaves protegidas, sem exposição após salvar</p>
            <p class="mt-1">As credenciais são criptografadas. Deixe os campos de chave vazios para manter os valores salvos; preencha apenas o que deseja substituir.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm font-medium text-red-800">Revise os campos indicados abaixo. Por segurança, as chaves digitadas precisam ser preenchidas novamente.</div>
        @endif

        <form method="POST" action="{{ route('platform.payment-settings.update') }}" autocomplete="off" class="flex flex-col gap-7">
            @csrf
            @method('PUT')

            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-950">
                <input type="hidden" name="enabled" value="0">
                <label class="flex items-start gap-3 font-bold"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled)) class="mt-1 size-5 accent-brand-700">Habilitar pagamentos online no ambiente selecionado</label>
                <p class="mt-3 text-sm leading-6">Ative somente após cadastrar Access Token e segredo do webhook. Testes registra a simulação sem quitar mensalidades nem liberar acesso real. Produção permite cobranças reais quando o cliente escolhe pagar. Desativar impede novos checkouts, mas não cancela assinaturas já autorizadas no Mercado Pago.</p>
                <x-input-error :messages="$errors->get('enabled')" />
            </section>

            <section class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 md:grid-cols-[1fr_auto] md:items-center">
                <div class="flex flex-col gap-2">
                    <h3 class="text-lg font-bold text-slate-950">Ambiente da integração</h3>
                    <p class="max-w-xl text-sm leading-6 text-slate-600">Mantenha Testes durante a configuração. As credenciais de cada ambiente ficam separadas.</p>
                </div>
                <div class="min-w-52">
                    <label for="environment" class="block text-sm font-bold text-slate-700">Ambiente selecionado</label>
                    <select id="environment" name="environment" required class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base font-semibold text-slate-950 focus:border-brand-600 focus:outline-2 focus:outline-offset-2 focus:outline-brand-600">
                        @foreach ($environments as $item)
                            <option value="{{ $item['key'] }}" @selected(old('environment', $environment) === $item['key'])>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('environment')" />
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($environments as $item)
                    <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="{{ $item['key'] }}-title">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:px-7">
                            <h3 id="{{ $item['key'] }}-title" class="text-xl font-bold text-slate-950">{{ $item['label'] }}</h3>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ $item['key'] === 'test' ? 'Desenvolvimento' : 'Conta real' }}</span>
                        </div>
                        <div class="flex flex-col gap-6 p-6 sm:p-7">
                            <div class="rounded-xl bg-brand-50 p-4 text-sm text-brand-950">
                                <p class="font-bold">URL do webhook · {{ $item['label'] }}</p>
                                <code class="mt-2 block break-all select-all">{{ $item['webhook_url'] }}</code>
                                <p class="mt-3 leading-6">No Mercado Pago, cadastre esta URL e marque “Planos e assinaturas” e “Pagamentos (legacy)”. Salve para gerar o segredo e depois cole-o abaixo. O domínio precisa estar acessível por HTTPS.</p>
                            </div>
                            @foreach ($item['credentials'] as $credential)
                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label for="{{ $credential['name'] }}" class="text-sm font-bold text-slate-700">{{ $credential['label'] }}</label>
                                        <span class="text-xs font-semibold {{ $credential['configured'] ? 'text-emerald-700' : 'text-slate-500' }}">{{ $credential['configured'] ? 'Credencial salva' : 'Não cadastrada' }}</span>
                                    </div>
                                    <input id="{{ $credential['name'] }}" name="{{ $credential['name'] }}" type="password" maxlength="4096" autocomplete="new-password" autocapitalize="none" spellcheck="false" placeholder="{{ $credential['configured'] ? 'Deixe vazio para manter a credencial' : 'Cole a credencial aqui' }}" aria-describedby="{{ $credential['name'] }}-help" @if ($errors->has($credential['name'])) aria-invalid="true" @endif class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-950 placeholder:text-sm placeholder:text-slate-500 focus:border-brand-600 focus:outline-2 focus:outline-offset-2 focus:outline-brand-600">
                                    <p id="{{ $credential['name'] }}-help" class="mt-2 text-xs leading-5 text-slate-500">{{ $credential['label'] === 'Segredo do webhook' ? 'Disponível na configuração de Webhooks da aplicação no Mercado Pago.' : 'Disponível nas credenciais da aplicação no Mercado Pago Developers.' }}</p>
                                    <x-input-error :messages="$errors->get($credential['name'])" />
                                    @if ($credential['configured'])
                                        <label class="mt-3 flex min-h-7 cursor-pointer items-center gap-2 text-sm text-slate-600">
                                            <input type="checkbox" name="remove_{{ $credential['name'] }}" value="1" class="size-4 rounded border-slate-300 accent-red-700">
                                            Remover a credencial salva
                                        </label>
                                    @endif
                                    <x-input-error :messages="$errors->get('remove_'.$credential['name'])" />
                                </div>
                            @endforeach

                            <div class="border-t border-slate-100 pt-5">
                                <label for="{{ $item['key'] }}_plan_id" class="block text-sm font-bold text-slate-700">Referência administrativa de plano <span class="font-normal text-slate-500">(não usada no checkout atual)</span></label>
                                <input id="{{ $item['key'] }}_plan_id" name="{{ $item['key'] }}_plan_id" type="text" maxlength="100" value="{{ old($item['key'].'_plan_id', $item['plan_id']) }}" autocomplete="off" spellcheck="false" aria-describedby="{{ $item['key'] }}-plan-help" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono text-sm text-slate-950 focus:border-brand-600 focus:outline-2 focus:outline-offset-2 focus:outline-brand-600">
                                <p id="{{ $item['key'] }}-plan-help" class="mt-2 text-xs leading-5 text-slate-500">Referência administrativa opcional. O checkout cria uma assinatura individual por empresa, sem depender do link genérico deste plano, para vincular corretamente os pagamentos.</p>
                                <x-input-error :messages="$errors->get($item['key'].'_plan_id')" />
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 md:flex-row md:items-center md:justify-between">
                <div class="max-w-2xl text-sm leading-6 text-slate-600">
                    <p class="font-bold text-slate-900">Salvar credenciais não inicia cobranças</p>
                    <p>Pix e cartão recorrente dependem da habilitação acima. O cartão é autorizado no ambiente seguro do Mercado Pago; nenhum dado de cartão fica no GiraComanda. Mantenha o agendador do Laravel ativo no Coolify para recuperar notificações perdidas.</p>
                    @if ($updatedAt)
                        <p class="mt-2 text-xs">Última atualização: {{ $updatedAt->format('d/m/Y H:i') }}.</p>
                    @endif
                </div>
                <button type="submit" class="min-h-12 shrink-0 rounded-xl bg-brand-700 px-6 py-3 text-base font-bold text-white hover:bg-brand-800 focus:outline-2 focus:outline-offset-2 focus:outline-brand-700">Salvar configurações</button>
            </div>
        </form>
    </div>
@endsection
