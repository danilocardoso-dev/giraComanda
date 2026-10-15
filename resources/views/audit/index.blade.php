@extends('layouts.app')

@section('title', 'Trilha de auditoria')

@section('content')
    <div>
        <h2 class="text-2xl font-bold text-slate-950">Histórico de ações</h2>
        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">Consulte quem realizou alterações importantes. Por segurança, senhas e valores anteriores dos campos não são copiados para este histórico.</p>
    </div>

    <form method="GET" action="{{ route('audit.index') }}" class="mt-6 grid gap-4 rounded-2xl border border-brand-100 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
        <div>
            <label for="start_date" class="block text-sm font-bold text-slate-700">Data inicial</label>
            <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $filters['start_date']) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
            @error('start_date') <p class="mt-2 text-xs font-semibold text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="end_date" class="block text-sm font-bold text-slate-700">Data final</label>
            <input id="end_date" name="end_date" type="date" value="{{ old('end_date', $filters['end_date']) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="area" class="block text-sm font-bold text-slate-700">Área</label>
            <select id="area" name="area" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
                <option value="">Todas</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->value }}" @selected(($filters['area'] ?? null) === $area->value)>{{ $area->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="event" class="block text-sm font-bold text-slate-700">Ação</label>
            <select id="event" name="event" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
                <option value="">Todas</option>
                @foreach ($events as $event)
                    <option value="{{ $event->value }}" @selected(($filters['event'] ?? null) === $event->value)>{{ $event->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="user_id" class="block text-sm font-bold text-slate-700">Usuário</label>
            <select id="user_id" name="user_id" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-slate-900 focus:border-brand-500 focus:ring-brand-500">
                <option value="">Todos</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Filtrar</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <div><h3 class="font-bold text-slate-950">Registros encontrados</h3><p class="mt-1 text-sm text-slate-500">{{ $auditLogs->total() }} {{ $auditLogs->total() === 1 ? 'ação registrada' : 'ações registradas' }}</p></div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-brand-50 text-left text-xs font-bold uppercase tracking-wider text-brand-800">
                    <tr><th class="px-5 py-4">Data e hora</th><th class="px-5 py-4">Usuário</th><th class="px-5 py-4">Área</th><th class="px-5 py-4">Ação realizada</th><th class="px-5 py-4">Campos alterados</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($auditLogs as $auditLog)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $auditLog->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $auditLog->user?->name ?? 'Usuário removido' }}</p>@if ($auditLog->ip_address)<p class="mt-1 text-xs text-slate-400">IP {{ $auditLog->ip_address }}</p>@endif</td>
                            <td class="px-5 py-4"><span class="inline-flex rounded-full bg-brand-100 px-2.5 py-1 text-xs font-bold text-brand-800">{{ $auditLog->area->label() }}</span></td>
                            <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $auditLog->description }}</p><p class="mt-1 text-xs text-slate-500">{{ $auditLog->subject_label }} · {{ $auditLog->event->label() }}</p></td>
                            <td class="px-5 py-4 text-slate-600">{{ $auditLog->changedFieldLabels() === [] ? '—' : implode(', ', $auditLog->changedFieldLabels()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Nenhuma ação encontrada para os filtros selecionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $auditLogs->links() }}</div>
@endsection
