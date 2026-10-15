<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Attendant = 'attendant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cashier => 'Caixa',
            self::Attendant => 'Atendente',
        };
    }
}
