@extends('layouts.guest')

@section('title', 'Redefinir senha')

@section('content')
    <div>
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Acesso</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Crie uma nova senha</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Use pelo menos oito caracteres e confirme a nova senha.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 grid gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <label for="password" class="text-sm font-semibold text-slate-700">Nova senha</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <label for="password_confirmation" class="text-sm font-semibold text-slate-700">Confirmar nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        </div>

        <button type="submit" class="w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Redefinir senha</button>
    </form>
@endsection
