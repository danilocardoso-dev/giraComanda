<?php

namespace App;

enum AuditArea: string
{
    case Users = 'users';
    case Customers = 'customers';
    case Cards = 'cards';
    case Products = 'products';
    case Stock = 'stock';
    case Cash = 'cash';
    case Tabs = 'tabs';
    case Receivables = 'receivables';
    case Payments = 'payments';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Usuários',
            self::Customers => 'Clientes',
            self::Cards => 'Cartões',
            self::Products => 'Produtos',
            self::Stock => 'Estoque',
            self::Cash => 'Caixa',
            self::Tabs => 'Comandas',
            self::Receivables => 'Contas a receber',
            self::Payments => 'Pagamentos',
            self::Settings => 'Configurações',
        };
    }
}
