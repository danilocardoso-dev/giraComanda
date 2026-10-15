<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentBranch;
use App\Models\Concerns\VisibleForCustomerSharing;
use App\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'tab_id', 'customer_id', 'cash_session_id', 'recorded_by', 'method', 'amount_cents', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToCurrentBranch, HasFactory, VisibleForCustomerSharing;

    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
