@extends('layouts.guest')

@section('title', 'Termos de uso')
@section('container-width', 'max-w-3xl')

@section('content')
    <article class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">GiraComanda</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Termos de uso do piloto</h2>
        <p class="mt-2 text-sm text-slate-500">Versão piloto · atualização em 11/10/2026</p>

        <div class="mt-7 grid gap-6 text-sm leading-7 text-slate-700">
            <section><h3 class="font-bold text-slate-950">Finalidade do sistema</h3><p>O GiraComanda apoia a gestão de comandas, clientes, caixa, estoque, contas a receber e relatórios do estabelecimento.</p></section>
            <section><h3 class="font-bold text-slate-950">Responsabilidade do estabelecimento</h3><p>O estabelecimento é responsável pela exatidão dos lançamentos, pela concessão de acessos, pela conferência diária do caixa e estoque e pelo cumprimento das obrigações fiscais e legais de sua atividade.</p></section>
            <section><h3 class="font-bold text-slate-950">Documento não fiscal</h3><p>Comprovantes e resumos emitidos pelo sistema são documentos não fiscais. O GiraComanda não substitui a emissão de nota ou cupom fiscal exigida pela legislação aplicável.</p></section>
            <section><h3 class="font-bold text-slate-950">Uso seguro</h3><p>Usuários devem manter suas credenciais protegidas, utilizar somente seu próprio acesso e comunicar imediatamente qualquer suspeita de uso indevido.</p></section>
            <section><h3 class="font-bold text-slate-950">Fase piloto</h3><p>Durante o piloto podem ocorrer ajustes de usabilidade e manutenção programada. Incidentes e divergências devem ser comunicados ao operador para análise e correção.</p></section>
        </div>

        <a href="{{ url()->previous() }}" class="mt-8 inline-flex rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Voltar</a>
    </article>
@endsection
