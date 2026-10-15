@extends('layouts.platform')

@section('title', 'Backups do sistema')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Proteção do banco de dados</h2>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">O sistema está preparado para criar uma cópia automática diariamente às 02:00. As cópias ficam em uma área privada e somente administradores podem baixá-las.</p>
        </div>
        <form method="POST" action="{{ route('platform.backups.store') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Criar backup agora</button>
        </form>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Backups disponíveis</p>
            <p class="mt-3 text-3xl font-bold text-brand-900">{{ $backups->count() }}</p>
        </article>
        <article class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Retenção</p>
            <p class="mt-3 text-3xl font-bold text-slate-950">{{ config('backup.retention_days') }} dias</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-emerald-800">Próxima rotina</p>
            <p class="mt-3 text-3xl font-bold text-emerald-950">02:00</p>
        </article>
    </section>

    <div class="mt-6 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="font-bold text-slate-950">Histórico de cópias</h3>
            <p class="mt-1 text-sm text-slate-500">Mantenha também uma cópia periódica fora deste computador.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr><th class="px-5 py-4">Arquivo</th><th class="px-5 py-4">Criado em</th><th class="px-5 py-4">Tamanho</th><th class="px-5 py-4 text-right">Ação</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($backups as $backup)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ $backup['name'] }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $backup['created_at']->format('d/m/Y H:i:s') }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ number_format($backup['size'] / 1024, 1, ',', '.') }} KB</td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('platform.backups.download', $backup['name']) }}" class="font-bold text-brand-700 hover:text-brand-900">Baixar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">Nenhum backup criado até o momento.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
        <p class="font-bold">Importante para o servidor</p>
        <p class="mt-2 text-sm leading-6 text-amber-900">A rotina automática depende do agendador do Laravel estar ativo no Windows. O backup manual acima já funciona mesmo antes dessa ativação.</p>
    </div>
@endsection
