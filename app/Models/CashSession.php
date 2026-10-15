<?php

namespace App\Models;

use App\CashSessionStatus;
use App\Models\Concerns\BelongsToCurrentBranch;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'opened_by', 'closed_by', 'status', 'opening_amount_cents', 'expected_amount_cents', 'counted_amount_cents', 'difference_cents', 'opened_at', 'closed_at', 'notes'])]
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use BelongsToCurrentBranch, HasFactory;

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opening_amount_cents' => 'integer',
            'expected_amount_cents' => 'integer',
            'counted_amount_cents' => 'integer',
            'difference_cents' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
