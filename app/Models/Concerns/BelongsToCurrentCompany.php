<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCurrentCompany
{
    public static function bootBelongsToCurrentCompany(): void
    {
        static::addGlobalScope('currentCompany', function (Builder $builder): void {
            if (app()->bound('currentCompany')) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('company_id'),
                    app('currentCompany')->id,
                );
            }
        });

        static::creating(function (self $model): void {
            if ($model->company_id === null && app()->bound('currentCompany')) {
                $model->company_id = app('currentCompany')->id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
