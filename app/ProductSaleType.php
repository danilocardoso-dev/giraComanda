<?php

namespace App;

enum ProductSaleType: string
{
    case Unit = 'unit';
    case Weight = 'weight';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unidade',
            self::Weight => 'Quilo',
        };
    }

    public function quantityLabel(): string
    {
        return match ($this) {
            self::Unit => 'un.',
            self::Weight => 'kg',
        };
    }
}
