@extends('layouts.guest')

@section('title', 'Começar teste grátis')
@section('container-width', 'max-w-xl')

@section('content')
    <div class="mb-8">
        <a href="{{ route('home') }}" class="text-sm font-bold text-brand-700 hover:text-brand-900">← Voltar para a apresentação</a>
        <p class="mt-6 text-sm font-bold uppercase tracking-[0.18em] text-brand-600">{{ config('giracomanda.subscription.trial_days') }} dias grátis · sem cartão</p>
        <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Comece a organizar sua operação</h2>
        <p class="mt-3 text-base leading-7 text-slate-600">Crie o acesso da sua empresa agora. A mensalidade de R$ {{ number_format(config('giracomanda.subscription.monthly_amount_cents') / 100, 0, ',', '.') }} só será necessária depois do período gratuito.</p>
    </div>

    <form method="POST" action="{{ route('trial.store') }}" class="grid gap-5 sm:grid-cols-2">
        @csrf

        <div class="sm:col-span-2">
            <label for="company_name" class="text-sm font-semibold text-slate-700">Nome do estabelecimento</label>
            <input id="company_name" name="company_name" value="{{ old('company_name') }}" required autofocus class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
            <x-input-error :messages="$errors->get('company_name')" />
        </div>

        <div>
            <label for="owner_name" class="text-sm font-semibold text-slate-700">Seu nome</label>
            <input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required autocomplete="name" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
            <x-input-error :messages="$errors->get('owner_name')" />
        </div>

        <div>
            <label for="billing_phone" class="text-sm font-semibold text-slate-700">WhatsApp</label>
            <input id="billing_phone" name="billing_phone" value="{{ old('billing_phone') }}" required data-mask="phone" inputmode="numeric" autocomplete="tel" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
            <x-input-error :messages="$errors->get('billing_phone')" />
        </div>

        <div class="sm:col-span-2">
            <label for="owner_email" class="text-sm font-semibold text-slate-700">E-mail de acesso</label>
            <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
            <x-input-error :messages="$errors->get('owner_email')" />
        </div>

        <div>
            <label for="owner_password" class="text-sm font-semibold text-slate-700">Senha</label>
            <input id="owner_password" name="owner_password" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
            <x-input-error :messages="$errors->get('owner_password')" />
        </div>

        <div>
            <label for="owner_password_confirmation" class="text-sm font-semibold text-slate-700">Confirmar senha</label>
            <input id="owner_password_confirmation" name="owner_password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none ring-brand-500 transition focus:border-brand-500 focus:ring-3">
        </div>

        <div class="sm:col-span-2">
            <label class="flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50 p-4 text-sm leading-6 text-slate-700">
                <input type="checkbox" name="terms" value="1" required class="mt-1 size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                <span>Li e aceito os <a href="{{ route('terms') }}" target="_blank" class="font-bold text-brand-700">Termos de uso</a> e a <a href="{{ route('privacy') }}" target="_blank" class="font-bold text-brand-700">Política de privacidade</a>.</span>
            </label>
            <x-input-error :messages="$errors->get('terms')" />
        </div>

        <button type="submit" class="sm:col-span-2 rounded-xl bg-brand-700 px-5 py-4 font-bold text-white shadow-lg shadow-brand-900/10 hover:bg-brand-800">Começar meus {{ config('giracomanda.subscription.trial_days') }} dias grátis</button>
        <p class="sm:col-span-2 text-center text-sm text-slate-600">Já é cliente? <a href="{{ route('login') }}" class="font-bold text-brand-700">Entrar no sistema</a></p>
    </form>
@endsection
