@extends('layouts.guest')

@section('title', 'Entrar')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Acesso da equipe</p>
        <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Bem-vindo de volta</h2>
        <p class="mt-3 text-slate-600">Entre com suas credenciais para acessar a operação.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <label for="password" class="text-sm font-semibold text-slate-700">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label class="flex items-center gap-3 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
            Manter conectado neste dispositivo
        </label>

        <button type="submit" class="w-full rounded-xl bg-brand-700 px-5 py-3.5 font-bold text-white shadow-lg shadow-brand-900/10 hover:bg-brand-800">
            Entrar no GiraComanda
        </button>
        <a href="{{ route('password.request') }}" class="block text-center text-sm font-semibold text-brand-700 hover:text-brand-900">Esqueci minha senha</a>
        <p class="text-center text-sm text-slate-600">Ainda não usa o sistema? <a href="{{ route('trial.create') }}" class="font-bold text-brand-700 hover:text-brand-900">Teste grátis por {{ config('giracomanda.subscription.trial_days') }} dias</a></p>
    </form>
@endsection
