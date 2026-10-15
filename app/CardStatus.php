<?php

namespace App;

enum CardStatus: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponível',
            self::InUse => 'Em uso',
            self::Blocked => 'Bloqueado',
        };
    }
}
