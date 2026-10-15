@props(['href', 'active' => false])

<a href="{{ $href }}"
    {{ $attributes->class([
        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold',
        'bg-white/12 text-white' => $active,
        'text-brand-100 hover:bg-white/8 hover:text-white' => ! $active,
    ]) }}>
    {{ $slot }}
</a>
