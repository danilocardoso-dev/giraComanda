@props([
    'card',
    'qrCodeDataUri',
    'settings',
    'company',
])

<section {{ $attributes->class(['print-card overflow-hidden rounded-[5mm] border border-dashed border-slate-400 bg-white p-[4mm] shadow-xl print:rounded-[3mm]']) }}>
    <div class="flex h-full items-center gap-[4mm]">
        <div class="grid size-[39mm] shrink-0 place-items-center rounded-[3mm] bg-white p-[1.5mm]">
            <img src="{{ $qrCodeDataUri }}" alt="QR Code do cartão {{ $card->code }}" class="size-full" width="600" height="600">
        </div>

        <div class="flex min-w-0 flex-1 flex-col self-stretch py-[1mm]">
            @if ($settings?->logo_path)
                <div class="flex items-center gap-[2mm]">
                    <div class="grid size-[18mm] shrink-0 place-items-center overflow-hidden rounded-[2.5mm] border border-brand-100 bg-brand-50 p-[0.5mm]">
                        <img src="{{ route('branding.logo', ['v' => $settings->updated_at?->timestamp]) }}" alt="Logo de {{ $settings->name }}" class="size-full object-contain">
                    </div>
                    <div class="min-w-0">
                        <p class="line-clamp-2 text-[2.2mm] font-black leading-tight text-brand-900">{{ $settings->name }}</p>
                        <p class="mt-[0.8mm] text-[1.6mm] font-bold uppercase leading-tight tracking-[0.1em] text-brand-600">Cartão de consumo</p>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-[2mm]">
                    <span class="grid size-[8mm] place-items-center rounded-[2mm] bg-warm-500 text-[4mm] font-black text-brand-950">{{ mb_strtoupper(mb_substr($settings?->name ?? $company->name, 0, 1)) }}</span>
                    <div>
                        <p class="max-w-[27mm] truncate text-[3mm] font-black tracking-tight text-brand-900">{{ $settings?->name ?? $company->name }}</p>
                        <p class="text-[2mm] uppercase tracking-[0.14em] text-brand-600">Cartão de consumo</p>
                    </div>
                </div>
            @endif

            <div class="my-auto">
                <p class="text-[2.2mm] font-bold uppercase tracking-[0.2em] text-slate-500">Identificação</p>
                <p class="mt-[1mm] text-[7mm] font-black tracking-tight text-brand-900">{{ $card->code }}</p>
            </div>

            <div>
                <p class="text-[2.2mm] leading-tight text-slate-600">Apresente este cartão para registrar seu consumo.</p>
                <p class="mt-[1mm] text-[1.6mm] font-semibold uppercase tracking-[0.1em] text-slate-400">Cartão reutilizável</p>
            </div>
        </div>
    </div>
</section>
