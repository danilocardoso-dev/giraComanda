<?php

namespace App\Http\Controllers;

use App\Actions\BuildSubscriptionPixQrCode;
use App\Actions\SynchronizeMercadoPago;
use App\Actions\SynchronizeSubscriptionInvoices;
use App\CompanyRole;
use App\Models\Company;
use App\Models\PlatformPaymentSetting;
use App\Models\SubscriptionCheckout;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionPortalController extends Controller
{
    public function __invoke(
        Request $request,
        SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices,
        BuildSubscriptionPixQrCode $buildSubscriptionPixQrCode,
        SynchronizeMercadoPago $synchronizeMercadoPago,
    ): RedirectResponse|View {
        $company = Company::query()
            ->with('subscription')
            ->whereHas('memberships', fn (Builder $query): Builder => $query
                ->where('user_id', $request->user()->id)
                ->where('active', true))
            ->firstOrFail();
        $subscription = $company->subscription;

        if ($subscription !== null) {
            $synchronizeSubscriptionInvoices->handle($subscription);

            if ($request->filled('preapproval_id')) {
                $preapprovalId = (string) $request->query('preapproval_id');
                $checkout = SubscriptionCheckout::query()
                    ->where('subscription_id', $subscription->id)
                    ->where('kind', 'card')
                    ->where(function (Builder $query) use ($preapprovalId): void {
                        $query->where('remote_id', $preapprovalId)
                            ->orWhereIn('status', ['creating', 'pending', 'authorized']);
                    })
                    ->latest('id')
                    ->first();

                if ($checkout !== null) {
                    if ($checkout->remote_id === null) {
                        $checkout->update(['remote_id' => $preapprovalId]);
                    }
                    try {
                        $feedback = $synchronizeMercadoPago->checkout($checkout);
                        if (! session()->has('payment_feedback')) {
                            session()->flash('payment_feedback', $feedback);
                        }
                    } catch (\Throwable) {
                        // Ignora falhas de comunicação externa para não quebrar a exibição do portal
                    }
                }

                if ($subscription->refresh()->allowsAccess()) {
                    return to_route('dashboard')->with('success', 'Pagamento confirmado! Acesso liberado.');
                }
            }
        }

        $openInvoice = $subscription?->invoices()
            ->whereNull('paid_at')
            ->oldest('due_date')
            ->first();
        $pixKey = trim((string) config('giracomanda.subscription.pix_key'));
        $pixCopyPaste = trim((string) config('giracomanda.subscription.pix_copy_paste'));
        $supportDigits = preg_replace('/\D/', '', (string) config('giracomanda.subscription.support_whatsapp'));

        $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();
        $canManageBilling = $request->user()->active && $company->memberships()->where('user_id', $request->user()->id)
            ->where('active', true)->where('role', CompanyRole::CompanyAdmin)->exists();
        $checkouts = $subscription ? SubscriptionCheckout::query()->where('subscription_id', $subscription->id)->latest('id')->limit(20)->get() : collect();
        $recurrence = $checkouts->first(fn (SubscriptionCheckout $checkout): bool => $checkout->isRecurring());
        $dynamicPix = $checkouts->first(fn (SubscriptionCheckout $checkout): bool => $checkout->kind === 'pix'
            && $checkout->subscription_invoice_id === $openInvoice?->id && in_array($checkout->status, ['pending', 'in_process'], true));
        if ($dynamicPix !== null && $dynamicPix->expires_at?->isFuture()) {
            $pixKey = '';
            $pixCopyPaste = $dynamicPix->pix_code;
        } elseif ($recurrence !== null || $dynamicPix !== null) {
            $pixKey = '';
            $pixCopyPaste = '';
        }

        if (in_array(strlen($supportDigits), [10, 11], true)) {
            $supportDigits = '55'.$supportDigits;
        }

        return view('subscriptions.show', [
            'company' => $company,
            'subscription' => $subscription,
            'openInvoice' => $openInvoice,
            'pixKey' => $pixKey !== '' ? $pixKey : null,
            'pixCopyPaste' => $pixCopyPaste !== '' ? $pixCopyPaste : null,
            'pixQrCodeDataUri' => $pixCopyPaste !== '' ? $buildSubscriptionPixQrCode($pixCopyPaste) : null,
            'supportWhatsappUrl' => $supportDigits !== '' ? "https://wa.me/{$supportDigits}" : null,
            'onlinePaymentsEnabled' => $settings?->ready() ?? false,
            'paymentEnvironment' => $settings?->environment ?? 'test',
            'canManageBilling' => $canManageBilling,
            'recurrence' => $recurrence,
            'dynamicPix' => $dynamicPix,
            'checkouts' => $checkouts,
            'failedCheckout' => ($subscription?->allowsAccess() || $openInvoice === null)
                ? null
                : $checkouts->first(fn (SubscriptionCheckout $checkout): bool => filled($checkout->failure_message) && in_array($checkout->status, ['creating', 'rejected'], true)),
        ]);
    }
}
