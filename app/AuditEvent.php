<?php

namespace App;

enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Criação',
            self::Updated => 'Alteração',
            self::Deleted => 'Exclusão',
        };
    }
}
