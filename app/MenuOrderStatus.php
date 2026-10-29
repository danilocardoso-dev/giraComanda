<?php

namespace App;

enum MenuOrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando confirmação',
            self::Accepted => 'Confirmado',
            self::Rejected => 'Recusado',
        };
    }
}
