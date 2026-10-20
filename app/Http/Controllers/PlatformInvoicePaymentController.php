<?php

namespace App\Http\Controllers;

use App\Actions\SettleSubscriptionInvoice;
use App\Http\Requests\StorePlatformInvoicePaymentRequest;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionInvoice;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformInvoicePaymentController extends Controller
{
    public function store(StorePlatformInvoicePaymentRequest $request, Company $company, SubscriptionInvoice $invoice, SettleSubscriptionInvoice $settle): RedirectResponse
    {
        abort_unless($invoice->subscription?->company_id === $company->id, 404);
        $validated = $request->validated();
        Cache::lock('subscription-checkout-'.$invoice->subscription_id, 90)->block(3, fn () => DB::transaction(function () use ($request, $company, $invoice, $validated, $settle): void {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($invoice->subscription_id);
            $invoice = SubscriptionInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if($invoice->paid_at !== null, 422, 'Esta mensalidade já foi paga.');
            abort_if(SubscriptionCheckout::query()->where('subscription_id', $subscription->id)->where('kind', 'card')
                ->whereNotIn('status', ['cancelled', 'rejected'])->exists(), 422, 'Cancele a recorrência no Mercado Pago antes de registrar uma baixa manual.');
            $settle->handle($subscription, $invoice, Carbon::parse($validated['paid_on'])->endOfDay(), $validated['payment_method'], $request->boolean('reactivate_access'));
            PlatformActivityLog::record(
                $request,
                $request->boolean('reactivate_access') ? 'invoice_paid_and_access_reactivated' : 'invoice_paid',
                'Pagamento de R$ '.number_format($invoice->amount_cents / 100, 2, ',', '.').' registrado'.($request->boolean('reactivate_access') ? ' e acesso reativado.' : '.'),
                $company,
            );
        }));

        return back()->with('success', 'Pagamento da mensalidade registrado.');
    }
}
