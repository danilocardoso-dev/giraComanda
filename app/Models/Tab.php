<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentBranch;
use App\TabStatus;
use Database\Factories\TabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'card_id', 'opened_by', 'closed_by', 'cancelled_by', 'customer_id', 'status', 'total_cents', 'opened_at', 'closed_at', 'cancelled_at', 'cancellation_reason'])]
class Tab extends Model
{
    /** @use HasFactory<TabFactory> */
    use BelongsToCurrentBranch, HasFactory;

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TabItem::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TabStatus::class,
            'total_cents' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
