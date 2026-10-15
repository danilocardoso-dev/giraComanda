@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Equipe e acessos</h2>
            <p class="mt-1 text-sm text-slate-600">Gerencie administradores, caixas e atendentes.</p>
        </div>
        <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Novo usuário</a>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr><th class="px-5 py-4">Usuário</th><th class="px-5 py-4">Perfil</th><th class="px-5 py-4">Situação</th><th class="px-5 py-4 text-right">Ação</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr class="hover:bg-brand-50/60">
                            <td class="px-5 py-4"><div class="font-semibold text-slate-900">{{ $user->name }}</div><div class="mt-1 text-xs text-slate-500">{{ $user->email }}</div></td>
                            <td class="px-5 py-4 text-slate-600">{{ $user->role->label() }}</td>
                            <td class="px-5 py-4"><span @class(['rounded-full px-2.5 py-1 text-xs font-bold', 'bg-emerald-100 text-emerald-800' => $user->active, 'bg-slate-200 text-slate-700' => ! $user->active])>{{ $user->active ? 'Ativo' : 'Inativo' }}</span></td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('users.edit', $user) }}" class="font-bold text-brand-700 hover:text-brand-900">Editar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-6">{{ $users->links() }}</div>
@endsection
