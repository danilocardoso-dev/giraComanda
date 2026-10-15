<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentBranch;
use App\Models\Concerns\VisibleForCustomerSharing;
use App\ReceivableStatus;
use Database\Factories\ReceivableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'customer_id', 'tab_id', 'amount_cents', 'paid_cents', 'due_date', 'status', 'paid_at'])]
class Receivable extends Model
{
    /** @use HasFactory<ReceivableFactory> */
    use BelongsToCurrentBranch, HasFactory, VisibleForCustomerSharing;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_cents' => 'integer',
            'due_date' => 'date',
            'status' => ReceivableStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
