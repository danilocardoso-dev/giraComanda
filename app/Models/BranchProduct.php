<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentBranch;
use Database\Factories\BranchProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'product_id', 'price_cents', 'stock_milliunits', 'min_stock_milliunits', 'active'])]
class BranchProduct extends Model
{
    /** @use HasFactory<BranchProductFactory> */
    use BelongsToCurrentBranch, HasFactory;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock_milliunits' => 'integer',
            'min_stock_milliunits' => 'integer',
            'active' => 'boolean',
        ];
    }
}
