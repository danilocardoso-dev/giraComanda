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
        if ($subscription->monthly_amount_cents <= 0) {
            return 0;
        }

        $cutoff = $through === null
            ? CarbonImmutable::today()
            : CarbonImmutable::instance($through)->startOfDay();

        if ($subscription->status === SubscriptionStatus::Trial) {
            return $this->synchronizeExpiredTrialInvoice($subscription, $cutoff);
        }

        if ($subscription->status !== SubscriptionStatus::Active) {
            return 0;
        }

        if ($subscription->paid_until !== null) {
            if ($subscription->paid_until->greaterThanOrEqualTo($cutoff) || $subscription->invoices()->whereNull('paid_at')->exists()) {
                return 0;
            }
            $targetAmount = $subscription->billing_cycle === 'yearly'
                ? $subscription->yearlyAmountCents()
                : $subscription->monthly_amount_cents;

            $invoice = $subscription->invoices()->firstOrCreate(['reference_month' => $cutoff->startOfMonth()->toDateString()], [
                'due_date' => $cutoff->toDateString(), 'amount_cents' => $targetAmount,
                'generated_automatically' => true, 'notes' => 'Renovação após o período pago. Sem acumular meses de acesso bloqueado.',
            ]);

            return $invoice->wasRecentlyCreated ? 1 : 0;
        }

        return $this->synchronizeActiveSubscriptionInvoices($subscription, $cutoff);
    }

    private function synchronizeExpiredTrialInvoice(Subscription $subscription, CarbonImmutable $cutoff): int
    {
        if ($subscription->trial_ends_at === null) {
            return 0;
        }

        $dueDate = CarbonImmutable::instance($subscription->trial_ends_at)->addDay()->startOfDay();

        if ($dueDate->isAfter($cutoff)) {
            return 0;
        }

        $referenceMonth = $dueDate->startOfMonth();
        $invoice = $subscription->invoices()
            ->whereDate('reference_month', $referenceMonth)
            ->first();

        if ($invoice === null) {
            $subscription->invoices()->create([
                'reference_month' => $referenceMonth,
                'due_date' => $dueDate,
                'amount_cents' => $subscription->monthly_amount_cents,
                'notes' => 'Primeira mensalidade após o teste gratuito.',
                'generated_automatically' => true,
            ]);

            return 1;
        }

        if (
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

            return 1;
        }

        return 0;
    }

    private function synchronizeActiveSubscriptionInvoices(Subscription $subscription, CarbonImmutable $cutoff): int
    {
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
        $cutoff = $through === null
            ? CarbonImmutable::today()
            : CarbonImmutable::instance($through)->startOfDay();

        Subscription::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where('status', SubscriptionStatus::Active->value)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->where('status', SubscriptionStatus::Trial->value)
                            ->whereNotNull('trial_ends_at')
                            ->whereDate('trial_ends_at', '<', $cutoff);
                    });
            })
            ->where('monthly_amount_cents', '>', 0)
            ->chunkById(100, function ($subscriptions) use (&$synchronizedCount, $cutoff): void {
                foreach ($subscriptions as $subscription) {
                    $synchronizedCount += $this->handle($subscription, $cutoff);
                }
            });

        return $synchronizedCount;
    }
}
