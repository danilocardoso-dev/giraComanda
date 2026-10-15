<?php

namespace App\Models;

use Database\Factories\SubscriptionInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_id', 'reference_month', 'due_date', 'amount_cents', 'paid_at', 'payment_method', 'notes', 'generated_automatically'])]
class SubscriptionInvoice extends Model
{
    /** @use HasFactory<SubscriptionInvoiceFactory> */
    use HasFactory;

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @param Builder<SubscriptionInvoice> $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('paid_at')->whereDate('due_date', '<', today());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'due_date' => 'date',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'generated_automatically' => 'boolean',
        ];
    }
}
