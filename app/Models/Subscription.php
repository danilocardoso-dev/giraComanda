<?php

namespace App\Models;

use App\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'status', 'billing_cycle', 'monthly_amount_cents', 'yearly_amount_cents', 'billing_day', 'billing_phone', 'started_at', 'trial_ends_at', 'notes', 'paid_until', 'manual_access_override'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    public function yearlyAmountCents(): int
    {
        return $this->yearly_amount_cents ?? config('giracomanda.subscription.yearly_amount_cents', 197000);
    }

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

    public function isTrialActive(): bool
    {
        return $this->status === SubscriptionStatus::Trial
            && ($this->trial_ends_at === null || $this->trial_ends_at->isToday() || $this->trial_ends_at->isFuture());
    }

    public function isTrialExpired(): bool
    {
        return $this->status === SubscriptionStatus::Trial
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast()
            && ! $this->trial_ends_at->isToday();
    }

    public function allowsAccess(): bool
    {
        return ($this->status === SubscriptionStatus::Active && ($this->paid_until === null || $this->paid_until->isFuture())) || $this->isTrialActive();
    }

    public function statusLabel(): string
    {
        if ($this->status === SubscriptionStatus::Active && $this->paid_until?->isPast()) {
            return 'Pagamento pendente';
        }
        if ($this->isTrialExpired()) {
            return 'Teste encerrado';
        }

        if ($this->allowsAccess() && $this->isOverdue()) {
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
            'yearly_amount_cents' => 'integer',
            'billing_day' => 'integer',
            'started_at' => 'date',
            'trial_ends_at' => 'date',
            'paid_until' => 'datetime',
            'manual_access_override' => 'boolean',
        ];
    }
}
