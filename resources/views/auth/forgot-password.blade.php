@extends('layouts.guest')

@section('title', 'Recuperar senha')

@section('content')
    <div>
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Acesso</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Recuperar senha</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Informe seu e-mail para receber um link de redefinição.</p>
    </div>

    <x-flash-message />

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 grid gap-5">
        @csrf
        <div>
            <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <button type="submit" class="w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Enviar instruções</button>
        <a href="{{ route('login') }}" class="text-center text-sm font-semibold text-brand-700 hover:text-brand-900">Voltar para o acesso</a>
    </form>
@endsection
