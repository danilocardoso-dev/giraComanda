<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Receivable;
use App\ReceivableStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BuildCustomerSummary
{
    /**
     * @return array{
     *     total_annotated_cents: int,
     *     total_paid_cents: int,
     *     open_balance_cents: int,
     *     available_credit_cents: int|null,
     *     annotations_count: int,
     *     last_consumption_at: mixed,
     *     recent_receivables: Collection<int, Receivable>
     * }
     */
    public function handle(Customer $customer): array
    {
        $receivables = $customer->receivables()->visibleForCustomerSharing();
        $totals = (clone $receivables)
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as total_annotated_cents')
            ->selectRaw('COALESCE(SUM(paid_cents), 0) as total_paid_cents')
            ->selectRaw('COUNT(*) as annotations_count')
            ->selectRaw('MAX(created_at) as last_consumption_at')
            ->first();
        $openBalanceCents = (int) (clone $receivables)
            ->where('status', ReceivableStatus::Open)
            ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) as open_balance_cents')
            ->value('open_balance_cents');

        return [
            'total_annotated_cents' => (int) $totals->total_annotated_cents,
            'total_paid_cents' => (int) $totals->total_paid_cents,
            'open_balance_cents' => $openBalanceCents,
            'available_credit_cents' => $customer->credit_limit_cents === null
                ? null
                : $customer->credit_limit_cents - $openBalanceCents,
            'annotations_count' => (int) $totals->annotations_count,
            'last_consumption_at' => $totals->last_consumption_at,
            'recent_receivables' => (clone $receivables)
                ->with($this->consumptionRelations())
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get(),
        ];
    }

    /** @return array<int|string, callable|string> */
    public function consumptionRelations(): array
    {
        return [
            'branch:id,name',
            'tab' => fn ($query) => $query
                ->withoutGlobalScope('currentBranch')
                ->whereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery
                    ->where('company_id', app('currentCompany')->id)),
            'tab.card' => fn ($query) => $query->withoutGlobalScope('currentBranch'),
            'tab.items.shares',
        ];
    }

    /** @return array<int|string, callable|string> */
    public function customerConsumptionRelations(): array
    {
        return [
            'receivables.branch:id,name',
            'receivables.tab' => fn ($query) => $query
                ->withoutGlobalScope('currentBranch')
                ->whereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery
                    ->where('company_id', app('currentCompany')->id)),
            'receivables.tab.card' => fn ($query) => $query->withoutGlobalScope('currentBranch'),
            'receivables.tab.items.shares',
        ];
    }
}
