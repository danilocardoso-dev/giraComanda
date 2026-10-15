@extends('layouts.app')

@section('title', 'Configurações')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-950">Dados do estabelecimento</h2>
            <p class="mt-1 text-sm text-slate-600">Defina a identidade visual, os dados gerais e o vencimento padrão das anotações.</p>
        </div>

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @method('PUT')

            <div class="grid gap-6 md:grid-cols-2">
                <section class="md:col-span-2 rounded-2xl border border-brand-100 bg-brand-50/60 p-5">
                    <div class="grid gap-5 sm:grid-cols-[220px_minmax(0,1fr)] sm:items-center">
                        <div data-company-logo-preview class="grid min-h-44 place-items-center overflow-hidden rounded-2xl border border-brand-100 bg-white p-3 shadow-sm">
                            @if ($settings->logo_path)
                                <img src="{{ route('branding.logo', ['v' => $settings->updated_at?->timestamp]) }}" alt="Logo atual de {{ $settings->name }}" data-company-logo-preview-image class="max-h-36 max-w-full object-contain drop-shadow-sm">
                            @else
                                <div data-company-logo-preview-fallback class="text-center">
                                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-warm-500 text-xl font-black text-brand-950">{{ mb_strtoupper(mb_substr($settings->name ?: $currentCompany->name, 0, 1)) }}</span>
                                    <p class="mt-3 text-xs font-bold text-slate-500">Sem logo personalizada</p>
                                </div>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Identidade visual</p>
                            <h3 class="mt-1 text-lg font-black text-slate-950">Logo do estabelecimento</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">A mesma logo será usada no painel e em todos os cartões reutilizáveis impressos.</p>
                            <label for="logo" class="mt-4 inline-flex cursor-pointer rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Selecionar logo</label>
                            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" data-company-logo-input class="sr-only">
                            <p data-company-logo-filename class="mt-2 text-xs text-slate-500">PNG com fundo transparente é a melhor opção. Máximo de 2 MB.</p>
                            <x-input-error :messages="$errors->get('logo')" />
                        </div>
                    </div>
                </section>

                <div class="md:col-span-2">
                    <label for="name" class="text-sm font-semibold text-slate-700">Nome do estabelecimento</label>
                    <input id="name" name="name" value="{{ old('name', $settings->name) }}" required maxlength="255"
                        class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <label for="document" class="text-sm font-semibold text-slate-700">CPF/CNPJ</label>
                    <input id="document" name="document" value="{{ old('document', $settings->document) }}" maxlength="20"
                        class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                    <x-input-error :messages="$errors->get('document')" />
                </div>

                <div>
                    <label for="phone" class="text-sm font-semibold text-slate-700">Telefone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $settings->phone) }}" maxlength="20"
                        class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                    <x-input-error :messages="$errors->get('phone')" />
                </div>

                <div>
                    <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $settings->email) }}" maxlength="255"
                        class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div>
                    <label for="general_due_day" class="text-sm font-semibold text-slate-700">Vencimento geral</label>
                    <input id="general_due_day" name="general_due_day" type="number" min="1" max="31" value="{{ old('general_due_day', $settings->general_due_day) }}" required
                        class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
                    <p class="mt-2 text-xs text-slate-500">Clientes sem uma data individual utilizarão este dia.</p>
                    <x-input-error :messages="$errors->get('general_due_day')" />
                </div>

                <div class="md:col-span-2">
                    <label for="address" class="text-sm font-semibold text-slate-700">Endereço</label>
                    <textarea id="address" name="address" rows="3" maxlength="1000"
                        class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">{{ old('address', $settings->address) }}</textarea>
                    <x-input-error :messages="$errors->get('address')" />
                </div>
            </div>

            <div class="mt-8 flex justify-end border-t border-slate-200 pt-6">
                <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Salvar configurações</button>
            </div>
        </form>
    </div>
@endsection
