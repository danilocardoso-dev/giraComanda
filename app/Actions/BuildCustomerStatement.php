<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Receivable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BuildCustomerStatement
{
    /**
     * @return array{
     *     month: string,
     *     period_label: string,
     *     period_start: CarbonImmutable,
     *     period_end: CarbonImmutable,
     *     opening_balance_cents: int,
     *     charges_total_cents: int,
     *     payments_total_cents: int,
     *     closing_balance_cents: int,
     *     receivables: Collection<int, Receivable>,
     *     payments: Collection<int, Payment>
     * }
     */
    public function handle(Customer $customer, string $month): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $periodStart = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $periodEnd = $periodStart->addMonth();
        $receivablesQuery = $customer->receivables()->visibleForCustomerSharing();
        $paymentsQuery = $customer->payments()->visibleForCustomerSharing();
        $openingChargesCents = (int) (clone $receivablesQuery)
            ->where('created_at', '<', $periodStart)
            ->sum('amount_cents');
        $openingPaymentsCents = (int) (clone $paymentsQuery)
            ->where('paid_at', '<', $periodStart)
            ->sum('amount_cents');
        $periodReceivablesQuery = (clone $receivablesQuery)
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<', $periodEnd);
        $periodPaymentsQuery = (clone $paymentsQuery)
            ->where('paid_at', '>=', $periodStart)
            ->where('paid_at', '<', $periodEnd);
        $openingBalanceCents = max(0, $openingChargesCents - $openingPaymentsCents);
        $chargesTotalCents = (int) (clone $periodReceivablesQuery)->sum('amount_cents');
        $paymentsTotalCents = (int) (clone $periodPaymentsQuery)->sum('amount_cents');

        return [
            'month' => $month,
            'period_label' => $periodStart->locale('pt_BR')->translatedFormat('F \d\e Y'),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'opening_balance_cents' => $openingBalanceCents,
            'charges_total_cents' => $chargesTotalCents,
            'payments_total_cents' => $paymentsTotalCents,
            'closing_balance_cents' => max(0, $openingBalanceCents + $chargesTotalCents - $paymentsTotalCents),
            'receivables' => $periodReceivablesQuery
                ->with([
                    'branch:id,name',
                    'tab' => fn ($query) => $query
                        ->withoutGlobalScope('currentBranch')
                        ->whereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery
                            ->where('company_id', app('currentCompany')->id)),
                    'tab.card' => fn ($query) => $query->withoutGlobalScope('currentBranch'),
                    'tab.items.shares',
                ])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(),
            'payments' => $periodPaymentsQuery
                ->with(['branch:id,name', 'recorder'])
                ->orderBy('paid_at')
                ->orderBy('id')
                ->get(),
        ];
    }
}
