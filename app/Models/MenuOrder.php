<?php

namespace App\Models;

use App\MenuOrderStatus;
use App\Models\Concerns\BelongsToCurrentBranch;
use App\TabStatus;
use Database\Factories\MenuOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'card_id', 'tab_id', 'reviewed_by', 'status', 'customer_name', 'notes', 'total_cents', 'rejection_reason', 'reviewed_at'])]
class MenuOrder extends Model
{
    /** @use HasFactory<MenuOrderFactory> */
    use BelongsToCurrentBranch, HasFactory;

    /**
     * Hours an order without a tab stays visible to whoever holds the card.
     */
    public const UNATTACHED_VISIBILITY_HOURS = 2;

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuOrderItem::class);
    }

    /**
     * Limit orders to the current visit, so a reused card never exposes a previous customer's orders.
     */
    public function scopeForCurrentVisit(Builder $query, Card $card, ?Tab $openTab): Builder
    {
        $lastFinishedTab = Tab::query()
            ->whereBelongsTo($card)
            ->where('status', '!=', TabStatus::Open)
            ->latest('id')
            ->first();
        $visitBoundary = collect([
            now()->subHours(self::UNATTACHED_VISIBILITY_HOURS),
            $lastFinishedTab?->closed_at,
            $lastFinishedTab?->cancelled_at,
        ])->filter()->max();

        return $query
            ->whereBelongsTo($card)
            ->where(function (Builder $query) use ($openTab, $visitBoundary): void {
                $query->where(fn (Builder $query): Builder => $query
                    ->whereNull('tab_id')
                    ->where('created_at', '>=', $visitBoundary));

                if ($openTab !== null) {
                    $query->orWhere('tab_id', $openTab->getKey());
                }
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MenuOrderStatus::class,
            'total_cents' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
