<?php

namespace App\Models;

use Database\Factories\TabItemShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tab_item_id', 'customer_id', 'amount_cents'])]
class TabItemShare extends Model
{
    /** @use HasFactory<TabItemShareFactory> */
    use HasFactory;

    public function item(): BelongsTo
    {
        return $this->belongsTo(TabItem::class, 'tab_item_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }
}
