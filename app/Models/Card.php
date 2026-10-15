<?php

namespace App\Models;

use App\CardStatus;
use App\Models\Concerns\BelongsToCurrentBranch;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'code', 'status', 'notes'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use BelongsToCurrentBranch, HasFactory;

    public function tabs(): HasMany
    {
        return $this->hasMany(Tab::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Card $card): void {
            $card->qr_token ??= Str::uuid()->toString();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CardStatus::class,
        ];
    }
}
