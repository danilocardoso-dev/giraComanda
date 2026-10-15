<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentCompany;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'name', 'cpf', 'phone', 'email', 'notes', 'credit_limit_cents', 'due_day', 'active'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToCurrentCompany, HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tabs(): HasMany
    {
        return $this->hasMany(Tab::class);
    }

    public function tabItemShares(): HasMany
    {
        return $this->hasMany(TabItemShare::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function monthlyStatementDeliveries(): HasMany
    {
        return $this->hasMany(MonthlyStatementDelivery::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('customerBranchVisibility', function (Builder $builder): void {
            if (app()->bound('currentBranch') && app()->bound('currentCompany') && ! app('currentCompany')->customer_sharing_enabled) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('branch_id'),
                    app('currentBranch')->id,
                );
            }
        });

        static::creating(function (Customer $customer): void {
            if ($customer->branch_id === null && app()->bound('currentBranch')) {
                $customer->branch_id = app('currentBranch')->id;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit_cents' => 'integer',
            'due_day' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected function maskedCpf(): Attribute
    {
        return Attribute::make(
            get: fn (): string => sprintf('***.%s.%s-**', substr($this->cpf, 3, 3), substr($this->cpf, 6, 3)),
        );
    }

    protected function formattedCpf(): Attribute
    {
        return Attribute::make(
            get: fn (): string => preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $this->cpf) ?? $this->cpf,
        );
    }

    protected function formattedPhone(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->phone === null) {
                    return null;
                }

                $pattern = mb_strlen($this->phone) === 11
                    ? '/^(\d{2})(\d{5})(\d{4})$/'
                    : '/^(\d{2})(\d{4})(\d{4})$/';

                return preg_replace($pattern, '($1) $2-$3', $this->phone) ?? $this->phone;
            },
        );
    }
}
