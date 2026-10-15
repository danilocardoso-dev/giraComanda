<?php

namespace App\Models;

use App\ProductSaleType;
use Database\Factories\TabItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tab_id', 'product_id', 'added_by', 'product_name', 'sale_type', 'quantity_milliunits', 'unit_price_cents', 'total_cents'])]
class TabItem extends Model
{
    /** @use HasFactory<TabItemFactory> */
    use HasFactory;

    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(TabItemShare::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_type' => ProductSaleType::class,
            'quantity_milliunits' => 'integer',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
