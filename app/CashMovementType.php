<?php

namespace App;

enum CashMovementType: string
{
    case Supply = 'supply';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Supply => 'Suprimento',
            self::Withdrawal => 'Sangria',
        };
    }
}
