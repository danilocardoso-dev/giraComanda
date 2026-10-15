@props([
    'id' => 'month',
    'name' => 'month',
    'value' => now()->format('Y-m'),
])

@php
    $monthNames = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];
    $selectedYear = (int) substr($value, 0, 4);
    $currentYear = (int) now()->format('Y');
    $firstYear = min($selectedYear, $currentYear - 5);
    $lastYear = max($selectedYear, $currentYear + 1);
@endphp

<select id="{{ $id }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'rounded-xl border border-slate-300 bg-white px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20']) }}>
    @for ($year = $lastYear; $year >= $firstYear; $year--)
        <optgroup label="{{ $year }}">
            @for ($monthNumber = 12; $monthNumber >= 1; $monthNumber--)
                @php($optionValue = sprintf('%04d-%02d', $year, $monthNumber))
                <option value="{{ $optionValue }}" @selected($optionValue === $value)>{{ $monthNames[$monthNumber] }} de {{ $year }}</option>
            @endfor
        </optgroup>
    @endfor
</select>
