@extends('layouts.guest')

@section('title', 'Criar nova senha')

@section('content')
    <div>
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-600">Senha temporária</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Crie sua nova senha</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">O administrador da plataforma redefiniu seu acesso. Escolha uma senha pessoal antes de continuar.</p>
    </div>

    <form method="POST" action="{{ route('password.required.update') }}" class="mt-8 grid gap-5">
        @csrf
        @method('PUT')

        <div>
            <label for="password" class="text-sm font-semibold text-slate-700">Nova senha</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" autofocus class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <label for="password_confirmation" class="text-sm font-semibold text-slate-700">Confirmar nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        </div>

        <button type="submit" class="w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Salvar e entrar</button>
    </form>
@endsection
