<?php

namespace App\Models;

use App\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'status', 'monthly_amount_cents', 'billing_day', 'billing_phone', 'started_at', 'trial_ends_at', 'notes'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isOverdue(): bool
    {
        return $this->invoices()
            ->whereNull('paid_at')
            ->whereDate('due_date', '<', today())
            ->exists();
    }

    public function statusLabel(): string
    {
        if ($this->status->allowsAccess() && $this->isOverdue()) {
            return 'Inadimplente';
        }

        return $this->status->label();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'monthly_amount_cents' => 'integer',
            'billing_day' => 'integer',
            'started_at' => 'date',
            'trial_ends_at' => 'date',
        ];
    }
}
