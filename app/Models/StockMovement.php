<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentBranch;
use App\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'product_id', 'recorded_by', 'tab_id', 'type', 'quantity_delta_milliunits', 'balance_after_milliunits', 'description', 'occurred_at'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToCurrentBranch, HasFactory;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_delta_milliunits' => 'integer',
            'balance_after_milliunits' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
