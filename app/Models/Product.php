<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentCompany;
use App\ProductSaleType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['company_id', 'menu_category_id', 'name', 'description', 'photo_path', 'sale_type', 'price_cents', 'stock_milliunits', 'min_stock_milliunits', 'active', 'show_on_menu'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToCurrentCompany, HasFactory;

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * Extra gallery photos shown after the main photo on the digital menu.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('position')->orderBy('id');
    }

    public function branchProducts(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    public function branchConfiguration(): HasOne
    {
        $relation = $this->hasOne(BranchProduct::class);

        if (app()->bound('currentBranch')) {
            $relation->where('branch_id', app('currentBranch')->id);
        }

        return $relation;
    }

    protected function priceCents(): Attribute
    {
        return Attribute::make(
            get: function (int $value): int {
                if (! app()->bound('currentBranch')) {
                    return $value;
                }

                return $this->branchConfiguration?->price_cents ?? $value;
            },
        );
    }

    protected function stockMilliunits(): Attribute
    {
        return Attribute::make(
            get: function (?int $value): ?int {
                if (! app()->bound('currentBranch')) {
                    return $value;
                }

                return $this->branchConfiguration?->stock_milliunits;
            },
        );
    }

    protected function minStockMilliunits(): Attribute
    {
        return Attribute::make(
            get: function (?int $value): ?int {
                if (! app()->bound('currentBranch')) {
                    return $value;
                }

                return $this->branchConfiguration?->min_stock_milliunits;
            },
        );
    }

    protected function active(): Attribute
    {
        return Attribute::make(
            get: function (bool $value): bool {
                if (! app()->bound('currentBranch')) {
                    return $value;
                }

                return $this->branchConfiguration?->active ?? false;
            },
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_type' => ProductSaleType::class,
            'price_cents' => 'integer',
            'stock_milliunits' => 'integer',
            'min_stock_milliunits' => 'integer',
            'active' => 'boolean',
            'show_on_menu' => 'boolean',
        ];
    }
}
