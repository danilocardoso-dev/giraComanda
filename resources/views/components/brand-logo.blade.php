@props([
    'showName' => true,
    'size' => 'md',
    'inverted' => false,
])

@php
    $markSize = match ($size) {
        'sm' => 'size-10',
        'lg' => 'size-16',
        'xl' => 'size-24',
        default => 'size-12',
    };

    $wordmarkSize = match ($size) {
        'sm' => 'text-lg',
        'lg' => 'text-2xl',
        'xl' => 'text-3xl',
        default => 'text-xl',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-3']) }}>
    <svg class="{{ $markSize }} shrink-0 drop-shadow-sm" viewBox="0 0 64 64" aria-hidden="true">
        <rect x="2" y="2" width="60" height="60" rx="19" class="fill-warm-500" />
        <path
            class="fill-brand-950"
            d="M32 13.5c-10.8 0-19.5 8.7-19.5 19.5S21.2 52.5 32 52.5c5.9 0 11.3-2.6 14.9-6.8V30.1H31.3v7.1h8.1v5.2a12 12 0 1 1 1.2-18.1l5.5-4.9A19.4 19.4 0 0 0 32 13.5Z"
        />
        <rect x="43" y="11" width="10" height="7" rx="2.5" class="fill-white" />
    </svg>

    @if ($showName)
        <span class="{{ $wordmarkSize }} font-black tracking-[-0.04em] {{ $inverted ? 'text-white' : 'text-brand-950' }}">
            Gira<span class="text-warm-500">Comanda</span>
        </span>
    @endif
</span>
