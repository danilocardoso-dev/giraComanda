@php
    $editing = isset($customer) && $customer->exists;
    $creditLimit = $editing && $customer->credit_limit_cents !== null
        ? number_format($customer->credit_limit_cents / 100, 2, '.', '')
        : '';
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="name" class="text-sm font-semibold text-slate-700">Nome completo</label>
        <input id="name" name="name" value="{{ old('name', $customer->name ?? '') }}" required maxlength="255"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <label for="cpf" class="text-sm font-semibold text-slate-700">CPF</label>
        <input id="cpf" name="cpf" value="{{ old('cpf', $customer->formatted_cpf ?? '') }}" required inputmode="numeric" maxlength="14" placeholder="000.000.000-00" data-mask="cpf" aria-describedby="cpf-help"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <p id="cpf-help" class="mt-2 text-xs text-slate-500" data-cpf-feedback>Obrigatório, exclusivo e validado automaticamente.</p>
        <x-input-error :messages="$errors->get('cpf')" />
    </div>

    <div>
        <label for="phone" class="text-sm font-semibold text-slate-700">Telefone / WhatsApp</label>
        <input id="phone" name="phone" value="{{ old('phone', $customer->formatted_phone ?? '') }}" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000" data-mask="phone"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <x-input-error :messages="$errors->get('phone')" />
    </div>

    <div>
        <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email', $customer->email ?? '') }}" maxlength="255"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <x-input-error :messages="$errors->get('email')" />
    </div>

    <div>
        <label for="credit_limit" class="text-sm font-semibold text-slate-700">Limite de crédito</label>
        <div class="mt-2 flex rounded-xl border border-slate-300 bg-white focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-500/20">
            <span class="border-r border-slate-200 px-4 py-3 text-slate-500">R$</span>
            <input id="credit_limit" name="credit_limit" type="number" min="0" step="0.01" value="{{ old('credit_limit', $creditLimit) }}" placeholder="Sem limite"
                class="min-w-0 flex-1 rounded-r-xl px-4 py-3 outline-none">
        </div>
        <p class="mt-2 text-xs text-slate-500">Opcional. Quando preenchido, o sistema apenas avisará ao ultrapassar.</p>
        <x-input-error :messages="$errors->get('credit_limit')" />
    </div>

    <div>
        <label for="due_day" class="text-sm font-semibold text-slate-700">Dia de vencimento individual</label>
        <input id="due_day" name="due_day" type="number" min="1" max="31" value="{{ old('due_day', $customer->due_day ?? '') }}" placeholder="Usar configuração geral"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <p class="mt-2 text-xs text-slate-500">Em branco, utiliza o dia geral do estabelecimento.</p>
        <x-input-error :messages="$errors->get('due_day')" />
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="text-sm font-semibold text-slate-700">Observações</label>
        <textarea id="notes" name="notes" rows="4" maxlength="2000"
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">{{ old('notes', $customer->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" />
    </div>

    <div class="md:col-span-2">
        <input type="hidden" name="active" value="0">
        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <input type="checkbox" name="active" value="1" @checked((bool) old('active', $customer->active ?? true))
                class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
            <span>
                <span class="block text-sm font-semibold text-slate-800">Cliente ativo</span>
                <span class="block text-xs text-slate-500">Clientes inativos permanecem no histórico, mas não recebem novas anotações.</span>
            </span>
        </label>
        <x-input-error :messages="$errors->get('active')" />
    </div>
</div>

<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6">
    <a href="{{ route('customers.index') }}" class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancelar</a>
    <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-800">
        {{ $editing ? 'Salvar alterações' : 'Cadastrar cliente' }}
    </button>
</div>
