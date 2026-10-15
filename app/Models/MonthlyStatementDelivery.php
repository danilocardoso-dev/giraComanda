<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentBranch;
use App\Models\Concerns\VisibleForCustomerSharing;
use Database\Factories\MonthlyStatementDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'customer_id', 'statement_month', 'sent_by', 'sent_at'])]
class MonthlyStatementDelivery extends Model
{
    /** @use HasFactory<MonthlyStatementDeliveryFactory> */
    use BelongsToCurrentBranch, HasFactory, VisibleForCustomerSharing;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'statement_month' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
