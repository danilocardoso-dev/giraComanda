<?php

namespace App\Actions;

use App\Models\Subscription;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SynchronizeSubscriptionInvoices
{
    public function handle(Subscription $subscription, ?CarbonInterface $through = null): int
    {
        if ($subscription->status !== SubscriptionStatus::Active || $subscription->monthly_amount_cents <= 0) {
            return 0;
        }

        $cutoff = $through === null
            ? CarbonImmutable::today()
            : CarbonImmutable::instance($through)->startOfDay();
        $startedAt = CarbonImmutable::instance($subscription->started_at)->startOfDay();

        if ($startedAt->isAfter($cutoff->endOfMonth())) {
            return 0;
        }

        $dueDate = $startedAt
            ->startOfMonth()
            ->day($subscription->billing_day);

        if ($dueDate->isBefore($startedAt)) {
            $dueDate = $dueDate->addMonthNoOverflow();
        }

        $synchronizedCount = 0;
        $lastReferenceMonth = $cutoff->startOfMonth();

        while ($dueDate->startOfMonth()->lessThanOrEqualTo($lastReferenceMonth)) {
            $referenceMonth = $dueDate->startOfMonth();
            $invoice = $subscription->invoices()
                ->whereDate('reference_month', $referenceMonth)
                ->first();

            if ($invoice === null) {
                $subscription->invoices()->create([
                    'reference_month' => $referenceMonth,
                    'due_date' => $dueDate,
                    'amount_cents' => $subscription->monthly_amount_cents,
                    'generated_automatically' => true,
                ]);
                $synchronizedCount++;
            } elseif (
                $invoice->generated_automatically
                && $invoice->paid_at === null
                && (
                    ! $invoice->due_date->isSameDay($dueDate)
                    || $invoice->amount_cents !== $subscription->monthly_amount_cents
                )
            ) {
                $invoice->update([
                    'due_date' => $dueDate,
                    'amount_cents' => $subscription->monthly_amount_cents,
                ]);
                $synchronizedCount++;
            }

            $dueDate = $dueDate->addMonthNoOverflow();
        }

        return $synchronizedCount;
    }

    public function handleAll(?CarbonInterface $through = null): int
    {
        $synchronizedCount = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('monthly_amount_cents', '>', 0)
            ->chunkById(100, function ($subscriptions) use (&$synchronizedCount, $through): void {
                foreach ($subscriptions as $subscription) {
                    $synchronizedCount += $this->handle($subscription, $through);
                }
            });

        return $synchronizedCount;
    }
}
