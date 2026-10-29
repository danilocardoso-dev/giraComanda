<?php

namespace App\Models;

use Database\Factories\MenuOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_order_id', 'product_id', 'product_name', 'quantity', 'unit_price_cents', 'total_cents'])]
class MenuOrderItem extends Model
{
    /** @use HasFactory<MenuOrderItemFactory> */
    use HasFactory;

    public function menuOrder(): BelongsTo
    {
        return $this->belongsTo(MenuOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
