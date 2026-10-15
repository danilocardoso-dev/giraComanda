<?php

namespace App\Models;

use App\CashMovementType;
use App\Models\Concerns\BelongsToCurrentBranch;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'cash_session_id', 'recorded_by', 'type', 'amount_cents', 'description', 'occurred_at'])]
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use BelongsToCurrentBranch, HasFactory;

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
