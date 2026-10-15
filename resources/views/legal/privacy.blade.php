@extends('layouts.guest')

@section('title', 'Privacidade')
@section('container-width', 'max-w-3xl')

@section('content')
    <article class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">GiraComanda</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Aviso de privacidade</h2>
        <p class="mt-2 text-sm text-slate-500">Versão piloto · atualização em 11/10/2026</p>

        <div class="mt-7 grid gap-6 text-sm leading-7 text-slate-700">
            <section><h3 class="font-bold text-slate-950">Dados tratados</h3><p>O sistema registra dados de usuários, estabelecimentos, filiais, clientes, CPF, telefone, consumo, pagamentos, comandas, estoque e registros de auditoria necessários à operação do restaurante.</p></section>
            <section><h3 class="font-bold text-slate-950">Finalidade</h3><p>Os dados são utilizados para identificar clientes, controlar consumo e crédito, prestar contas, operar o caixa, gerar relatórios e proteger a rastreabilidade das operações.</p></section>
            <section><h3 class="font-bold text-slate-950">Acesso e compartilhamento</h3><p>O acesso é limitado por empresa, filial e perfil de usuário. Clientes somente são compartilhados entre filiais quando a empresa habilita essa opção. Os dados não são comercializados.</p></section>
            <section><h3 class="font-bold text-slate-950">Conservação e segurança</h3><p>Os registros são mantidos enquanto necessários à operação, a obrigações aplicáveis e à defesa de direitos. O operador utiliza controle de acesso, auditoria, comunicação protegida e cópias de segurança.</p></section>
            <section><h3 class="font-bold text-slate-950">Solicitações</h3><p>Pedidos de confirmação, correção, exportação ou exclusão devem ser encaminhados ao estabelecimento responsável pelo cadastro. Quando necessário, o operador da plataforma auxiliará o estabelecimento no atendimento.</p></section>
        </div>

        <a href="{{ url()->previous() }}" class="mt-8 inline-flex rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Voltar</a>
    </article>
@endsection
