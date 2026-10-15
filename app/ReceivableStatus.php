<?php

namespace App;

enum ReceivableStatus: string
{
    case Open = 'open';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'A receber',
            self::Paid => 'Pago',
        };
    }
}
