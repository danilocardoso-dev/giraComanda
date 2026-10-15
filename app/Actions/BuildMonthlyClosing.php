<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\MonthlyStatementDelivery;
use App\Models\Payment;
use App\Models\Receivable;
use App\ReceivableStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildMonthlyClosing
{
    /**
     * @return array{
     *     month: string,
     *     period_label: string,
     *     period_start: CarbonImmutable,
     *     period_end: CarbonImmutable,
     *     customers: LengthAwarePaginator,
     *     charges_total_cents: int,
     *     payments_total_cents: int,
     *     open_total_cents: int,
     *     overdue_total_cents: int,
     *     not_due_total_cents: int,
     *     sent_count: int
     * }
     */
    public function handle(string $month): array
    {
        $periodStart = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $periodEnd = $periodStart->addMonth();
        $customers = Customer::query()
            ->where(function ($query) use ($periodStart, $periodEnd): void {
                $query->whereHas('receivables', fn ($query) => $query
                    ->visibleForCustomerSharing()
                    ->where('status', ReceivableStatus::Open)
                    ->where('created_at', '<', $periodEnd))
                    ->orWhereHas('receivables', fn ($query) => $query
                        ->visibleForCustomerSharing()
                        ->where('created_at', '>=', $periodStart)
                        ->where('created_at', '<', $periodEnd))
                    ->orWhereHas('payments', fn ($query) => $query
                        ->visibleForCustomerSharing()
                        ->where('paid_at', '>=', $periodStart)
                        ->where('paid_at', '<', $periodEnd));
            })
            ->addSelect([
                'opening_charges_cents' => Receivable::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('created_at', '<', $periodStart),
                'opening_payments_cents' => Payment::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('paid_at', '<', $periodStart),
                'period_charges_cents' => Receivable::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('created_at', '>=', $periodStart)
                    ->where('created_at', '<', $periodEnd),
                'period_payments_cents' => Payment::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('paid_at', '>=', $periodStart)
                    ->where('paid_at', '<', $periodEnd),
                'open_balance_cents' => Receivable::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('status', ReceivableStatus::Open),
                'overdue_balance_cents' => Receivable::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('status', ReceivableStatus::Open)
                    ->whereDate('due_date', '<', today()),
                'not_due_balance_cents' => Receivable::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('status', ReceivableStatus::Open)
                    ->whereDate('due_date', '>=', today()),
            ])
            ->withMin(['receivables as oldest_overdue_date' => fn ($query) => $query
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open)
                ->whereDate('due_date', '<', today())], 'due_date')
            ->withMin(['receivables as next_not_due_date' => fn ($query) => $query
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open)
                ->whereDate('due_date', '>=', today())], 'due_date')
            ->with(['monthlyStatementDeliveries' => fn ($query) => $query
                ->visibleForCustomerSharing()
                ->whereDate('statement_month', $periodStart->toDateString())
                ->with('sender:id,name')])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $customers->getCollection()->each(function (Customer $customer): void {
            $openingBalanceCents = max(0, (int) $customer->opening_charges_cents - (int) $customer->opening_payments_cents);
            $customer->setAttribute('opening_balance_cents', $openingBalanceCents);
            $customer->setAttribute('closing_balance_cents', max(
                0,
                $openingBalanceCents + (int) $customer->period_charges_cents - (int) $customer->period_payments_cents,
            ));
        });

        return [
            'month' => $month,
            'period_label' => $periodStart->locale('pt_BR')->translatedFormat('F \d\e Y'),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'customers' => $customers,
            'charges_total_cents' => (int) Receivable::query()
                ->visibleForCustomerSharing()
                ->where('created_at', '>=', $periodStart)
                ->where('created_at', '<', $periodEnd)
                ->sum('amount_cents'),
            'payments_total_cents' => (int) Payment::query()
                ->visibleForCustomerSharing()
                ->whereNotNull('customer_id')
                ->where('paid_at', '>=', $periodStart)
                ->where('paid_at', '<', $periodEnd)
                ->sum('amount_cents'),
            'open_total_cents' => (int) Receivable::query()
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open)
                ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) as open_balance')
                ->value('open_balance'),
            'overdue_total_cents' => (int) Receivable::query()
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open)
                ->whereDate('due_date', '<', today())
                ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) as overdue_balance')
                ->value('overdue_balance'),
            'not_due_total_cents' => (int) Receivable::query()
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open)
                ->whereDate('due_date', '>=', today())
                ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) as not_due_balance')
                ->value('not_due_balance'),
            'sent_count' => MonthlyStatementDelivery::query()
                ->visibleForCustomerSharing()
                ->whereDate('statement_month', $periodStart->toDateString())
                ->count(),
        ];
    }
}
