@php($editing = isset($user) && $user->exists)

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="name" class="text-sm font-semibold text-slate-700">Nome</label>
        <input id="name" name="name" value="{{ old('name', $user->name ?? '') }}" required maxlength="255"
            class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required maxlength="255"
            class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        <x-input-error :messages="$errors->get('email')" />
    </div>

    <div>
        <label for="role" class="text-sm font-semibold text-slate-700">Perfil</label>
        <select id="role" name="role" required
            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', isset($user) ? $user->role->value : \App\UserRole::Attendant->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('role')" />
    </div>

    <div class="md:row-span-2">
        <input type="hidden" name="active" value="0">
        <label class="mt-7 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <input type="checkbox" name="active" value="1" @checked((bool) old('active', $user->active ?? true))
                class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
            <span class="text-sm font-semibold text-slate-800">Usuário ativo</span>
        </label>
        <x-input-error :messages="$errors->get('active')" />
    </div>

    <div>
        <label for="password" class="text-sm font-semibold text-slate-700">{{ $editing ? 'Nova senha' : 'Senha' }}</label>
        <input id="password" name="password" type="password" @required(! $editing) autocomplete="new-password"
            class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
        @if ($editing)<p class="mt-2 text-xs text-slate-500">Deixe em branco para manter a senha atual.</p>@endif
        <x-input-error :messages="$errors->get('password')" />
    </div>

    <div>
        <label for="password_confirmation" class="text-sm font-semibold text-slate-700">Confirmar senha</label>
        <input id="password_confirmation" name="password_confirmation" type="password" @required(! $editing) autocomplete="new-password"
            class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20">
    </div>
</div>

<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6">
    <a href="{{ route('users.index') }}" class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancelar</a>
    <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">
        {{ $editing ? 'Salvar alterações' : 'Cadastrar usuário' }}
    </button>
</div>
