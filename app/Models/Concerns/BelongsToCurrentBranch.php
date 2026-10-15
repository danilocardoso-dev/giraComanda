<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCurrentBranch
{
    public static function bootBelongsToCurrentBranch(): void
    {
        static::addGlobalScope('currentBranch', function (Builder $builder): void {
            if (app()->bound('currentBranch')) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('branch_id'),
                    app('currentBranch')->id,
                );
            }
        });

        static::creating(function (self $model): void {
            if ($model->branch_id === null && app()->bound('currentBranch')) {
                $model->branch_id = app('currentBranch')->id;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
