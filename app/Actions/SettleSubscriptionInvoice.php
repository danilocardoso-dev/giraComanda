<?php

namespace App\Actions;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\SubscriptionStatus;
use Carbon\CarbonInterface;

class SettleSubscriptionInvoice
{
    /** Caller must hold the subscription and invoice row locks inside a transaction. */
    public function handle(Subscription $subscription, SubscriptionInvoice $invoice, CarbonInterface $paidAt, string $method, bool $reactivate, bool $automatic = false): void
    {
        $invoice->update(['paid_at' => $paidAt, 'payment_method' => $method]);

        if (! $reactivate) {
            return;
        }
        if ($automatic && $subscription->manual_access_override && $subscription->status === SubscriptionStatus::Active) {
            return;
        }

        $wasTrial = $subscription->status === SubscriptionStatus::Trial;
        if ($wasTrial && $automatic && $invoice->generated_automatically
            && ! $invoice->reference_month->isSameMonth($paidAt)) {
            $invoice->update(['reference_month' => $paidAt->copy()->startOfMonth(), 'due_date' => $paidAt->toDateString()]);
        }
        $periodStart = $automatic || $subscription->paid_until !== null ? $paidAt->copy() : $invoice->due_date->copy();
        if ($subscription->paid_until?->gt($periodStart)) {
            $periodStart = $subscription->paid_until->copy();
        }
        $periodEnd = $periodStart->copy()->addMonthNoOverflow()->endOfDay();
        $subscription->update([
            'status' => SubscriptionStatus::Active, 'trial_ends_at' => null,
            'paid_until' => $automatic || $subscription->paid_until !== null
                ? ($subscription->paid_until?->gt($periodEnd) ? $subscription->paid_until : $periodEnd) : null,
            ...($wasTrial ? ['started_at' => $periodStart->toDateString(), 'billing_day' => min($periodStart->day, 28)] : []),
        ]);
    }
}
