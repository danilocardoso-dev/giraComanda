@if (session('success'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
        <span class="mt-0.5 inline-block size-2 rounded-full bg-emerald-500"></span>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if (session('payment_feedback'))
    @php($feedback = session('payment_feedback'))
    <div role="{{ $feedback['level'] === 'warning' ? 'alert' : 'status' }}" @class([
        'mb-6 rounded-2xl border px-4 py-3 text-sm font-medium leading-6',
        'border-emerald-200 bg-emerald-50 text-emerald-900' => $feedback['level'] === 'success',
        'border-amber-200 bg-amber-50 text-amber-950' => $feedback['level'] === 'warning',
        'border-slate-300 bg-slate-50 text-slate-800' => ! in_array($feedback['level'], ['success', 'warning'], true),
    ])>{{ $feedback['message'] }}</div>
@endif
