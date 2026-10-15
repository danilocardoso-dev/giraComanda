<?php

namespace App;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Em teste',
            self::Active => 'Em dia',
            self::Suspended => 'Suspenso',
            self::Cancelled => 'Cancelado',
        };
    }

    public function allowsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }
}
