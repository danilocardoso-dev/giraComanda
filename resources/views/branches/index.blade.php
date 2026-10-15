@extends('layouts.guest')

@section('title', 'Escolher filial')
@section('container-width', 'max-w-3xl')

@section('content')
    <div class="w-full max-w-3xl rounded-3xl border border-brand-100 bg-white p-7 shadow-xl sm:p-10">
        <div class="flex items-start justify-between gap-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-600">GiraComanda</p>
                <h1 class="mt-2 text-3xl font-bold text-slate-950">Escolha onde deseja trabalhar</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">Cada filial mantém sua própria operação de comandas, caixa e estoque.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-semibold text-red-700 hover:text-red-900" type="submit">Sair</button>
            </form>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @foreach ($branches as $branch)
                <form method="POST" action="{{ route('branches.activate', $branch) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl border border-brand-200 bg-brand-50 p-5 text-left transition hover:border-brand-500 hover:bg-brand-100">
                        <span class="block text-xs font-bold uppercase tracking-[0.15em] text-brand-600">{{ $branch->company->name }}</span>
                        <span class="mt-2 block text-xl font-bold text-slate-950">{{ $branch->name }}</span>
                        <span class="mt-3 block text-sm font-semibold text-brand-700">Acessar filial →</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endsection
