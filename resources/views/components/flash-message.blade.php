@if (session('success'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
        <span class="mt-0.5 inline-block size-2 rounded-full bg-emerald-500"></span>
        <span>{{ session('success') }}</span>
    </div>
@endif
