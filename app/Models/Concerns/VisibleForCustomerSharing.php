<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait VisibleForCustomerSharing
{
    public function scopeVisibleForCustomerSharing(Builder $query): Builder
    {
        if (! app()->bound('currentCompany') || ! app('currentCompany')->customer_sharing_enabled) {
            return $query;
        }

        return $query
            ->withoutGlobalScope('currentBranch')
            ->whereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery
                ->where('company_id', app('currentCompany')->id));
    }
}
