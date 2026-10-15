<?php

namespace App;

enum CompanyRole: string
{
    case CompanyAdmin = 'company_admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::CompanyAdmin => 'Administrador da empresa',
            self::Member => 'Colaborador',
        };
    }
}
