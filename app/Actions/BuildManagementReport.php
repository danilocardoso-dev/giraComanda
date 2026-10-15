<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\PaymentMethod;
use App\ReceivableStatus;
use App\TabStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BuildManagementReport
{
    /**
     * @return array<string, mixed>
     */
    public function handle(
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        Company $company,
        ?Branch $branch,
    ): array {
        $periodEndExclusive = $periodEnd->addDay();
        $closedTabsQuery = $this->forReportBranches(Tab::query(), $company, $branch)
            ->where('status', TabStatus::Closed)
            ->where('closed_at', '>=', $periodStart)
            ->where('closed_at', '<', $periodEndExclusive);
        $paymentsQuery = $this->forReportBranches(Payment::query(), $company, $branch)
            ->where('paid_at', '>=', $periodStart)
            ->where('paid_at', '<', $periodEndExclusive);
        $receivablesQuery = $this->forReportBranches(Receivable::query(), $company, $branch)
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<', $periodEndExclusive);
        $closedTabsCount = (clone $closedTabsQuery)->count();
        $grossSalesCents = (int) (clone $closedTabsQuery)->sum('total_cents');

        return [
            'closedTabsCount' => $closedTabsCount,
            'grossSalesCents' => $grossSalesCents,
            'averageTicketCents' => $closedTabsCount === 0 ? 0 : (int) round($grossSalesCents / $closedTabsCount),
            'receivedTotalCents' => (int) (clone $paymentsQuery)->sum('amount_cents'),
            'annotationsTotalCents' => (int) (clone $receivablesQuery)->sum('amount_cents'),
            'openReceivablesCents' => (int) $this->forReportBranches(Receivable::query(), $company, $branch)
                ->where('status', ReceivableStatus::Open)
                ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) as open_balance')
                ->value('open_balance'),
            'paymentMethods' => PaymentMethod::immediateCases(),
            'totalsByPaymentMethod' => (clone $paymentsQuery)
                ->selectRaw('method, SUM(amount_cents) as total_cents')
                ->groupBy('method')
                ->pluck('total_cents', 'method'),
            'dailySales' => (clone $closedTabsQuery)
                ->selectRaw('DATE(closed_at) as sale_date, SUM(total_cents) as total_cents, COUNT(*) as tabs_count')
                ->groupBy('sale_date')
                ->orderBy('sale_date')
                ->get(),
            'topProducts' => TabItem::query()
                ->select(['product_name', 'sale_type'])
                ->selectRaw('SUM(quantity_milliunits) as quantity_milliunits, SUM(total_cents) as total_cents, COUNT(*) as launches_count')
                ->whereHas('tab', function (Builder $query) use ($company, $branch, $periodStart, $periodEndExclusive): void {
                    $this->forReportBranches($query, $company, $branch)
                        ->where('status', TabStatus::Closed)
                        ->where('closed_at', '>=', $periodStart)
                        ->where('closed_at', '<', $periodEndExclusive);
                })
                ->groupBy('product_name', 'sale_type')
                ->orderByDesc('total_cents')
                ->orderBy('product_name')
                ->limit(10)
                ->get(),
            'lowStockProducts' => $this->forReportBranches(BranchProduct::query(), $company, $branch)
                ->with(['branch:id,name', 'product:id,name,sale_type'])
                ->where('active', true)
                ->whereNotNull('stock_milliunits')
                ->whereNotNull('min_stock_milliunits')
                ->whereColumn('stock_milliunits', '<=', 'min_stock_milliunits')
                ->orderBy('branch_id')
                ->orderBy('product_id')
                ->limit(10)
                ->get(),
        ];
    }

    private function forReportBranches(Builder $query, Company $company, ?Branch $branch): Builder
    {
        return $query
            ->withoutGlobalScope('currentBranch')
            ->when(
                $branch !== null,
                fn (Builder $query): Builder => $query->where(
                    $query->getModel()->qualifyColumn('branch_id'),
                    $branch->id,
                ),
                fn (Builder $query): Builder => $query->whereHas(
                    'branch',
                    fn (Builder $branchQuery): Builder => $branchQuery->whereBelongsTo($company),
                ),
            );
    }
}
