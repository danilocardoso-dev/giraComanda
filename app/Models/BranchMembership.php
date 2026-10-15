<?php

namespace App\Models;

use App\BranchRole;
use Database\Factories\BranchMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'user_id', 'role', 'active'])]
class BranchMembership extends Model
{
    /** @use HasFactory<BranchMembershipFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => BranchRole::class,
            'active' => 'boolean',
        ];
    }
}
