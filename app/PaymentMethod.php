<?php

namespace App;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Pix = 'pix';
    case DebitCard = 'debit_card';
    case CreditCard = 'credit_card';
    case Account = 'account';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Dinheiro',
            self::Pix => 'PIX',
            self::DebitCard => 'Cartão de débito',
            self::CreditCard => 'Cartão de crédito',
            self::Account => 'Anotação do cliente',
            self::Mixed => 'Pagamento misto',
        };
    }

    public function createsReceivable(): bool
    {
        return $this === self::Account;
    }

    /** @return array<int, self> */
    public static function immediateCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $method): bool => ! $method->createsReceivable() && $method !== self::Mixed,
        ));
    }
}
