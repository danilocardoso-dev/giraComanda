<?php

namespace App;

enum BranchRole: string
{
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Attendant = 'attendant';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Gerente',
            self::Cashier => 'Caixa',
            self::Attendant => 'Atendente',
        };
    }
}
