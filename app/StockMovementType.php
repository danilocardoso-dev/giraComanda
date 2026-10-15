<?php

namespace App;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Entry = 'entry';
    case ManualOut = 'manual_out';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case SaleReversal = 'sale_reversal';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Estoque inicial',
            self::Entry => 'Entrada',
            self::ManualOut => 'Saída manual',
            self::Adjustment => 'Ajuste de inventário',
            self::Sale => 'Venda em comanda',
            self::SaleReversal => 'Cancelamento de item',
        };
    }

    /** @return array<int, self> */
    public static function manualCases(): array
    {
        return [self::Entry, self::ManualOut, self::Adjustment];
    }
}
