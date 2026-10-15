<?php

namespace App\Http\Controllers;

use App\Actions\SynchronizeSubscriptionInvoices;
use App\Http\Requests\UpdatePlatformSubscriptionRequest;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use Illuminate\Http\RedirectResponse;

class PlatformSubscriptionController extends Controller
{
    public function update(
        UpdatePlatformSubscriptionRequest $request,
        Company $company,
        SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices,
    ): RedirectResponse {
        $subscription = $company->subscription()->firstOrFail();
        $validated = $request->validated();

        $subscription->update([
            'status' => $validated['status'],
            'monthly_amount_cents' => (int) round((float) $validated['monthly_amount'] * 100),
            'billing_day' => $validated['billing_day'],
            'billing_phone' => $validated['billing_phone'] ?? null,
            'started_at' => $validated['started_at'],
            'trial_ends_at' => $validated['trial_ends_at'],
            'notes' => $validated['notes'],
        ]);

        $synchronizeSubscriptionInvoices->handle($subscription);

        PlatformActivityLog::record(
            $request,
            'subscription_updated',
            "Assinatura atualizada para {$subscription->status->label()} com mensalidade de R$ ".number_format($subscription->monthly_amount_cents / 100, 2, ',', '.'),
            $company,
        );

        return back()->with('success', 'Assinatura atualizada com sucesso.');
    }
}
