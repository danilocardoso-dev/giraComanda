<?php

namespace App\Http\Controllers;

use App\Actions\CancelSubscriptionCheckout;
use App\Actions\SynchronizeSubscriptionInvoices;
use App\Http\Requests\UpdatePlatformSubscriptionRequest;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use App\Models\SubscriptionCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PlatformSubscriptionController extends Controller
{
    public function update(
        UpdatePlatformSubscriptionRequest $request,
        Company $company,
        SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices,
        CancelSubscriptionCheckout $cancelCheckout,
    ): RedirectResponse {
        $subscription = $company->subscription()->firstOrFail();
        $validated = $request->validated();

        $newAmount = (int) round((float) $validated['monthly_amount'] * 100);
        if ($newAmount !== $subscription->monthly_amount_cents || in_array($validated['status'], ['suspended', 'cancelled'], true)) {
            foreach (SubscriptionCheckout::query()->where('subscription_id', $subscription->id)->where('kind', 'card')->whereNotIn('status', ['cancelled', 'rejected'])->get() as $checkout) {
                try {
                    $cancelCheckout->handle($checkout);
                } catch (RuntimeException $exception) {
                    throw ValidationException::withMessages(['status' => 'Não foi possível cancelar a recorrência anterior. '.$exception->getMessage()]);
                }
            }
        }

        $subscription->update([
            'status' => $validated['status'],
            'monthly_amount_cents' => (int) round((float) $validated['monthly_amount'] * 100),
            'billing_day' => $validated['billing_day'],
            'billing_phone' => $validated['billing_phone'] ?? null,
            'started_at' => $validated['started_at'],
            'trial_ends_at' => $validated['trial_ends_at'],
            'notes' => $validated['notes'],
            // Saving Active in the master panel is an explicit administrative override.
            'paid_until' => $validated['status'] === 'active' ? null : $subscription->paid_until,
            'manual_access_override' => $validated['status'] === 'active',
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
