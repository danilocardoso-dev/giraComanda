<?php

namespace App\Http\Controllers;

use App\Actions\BuildSubscriptionPixQrCode;
use App\Actions\SynchronizeSubscriptionInvoices;
use App\CompanyRole;
use App\Models\Company;
use App\Models\PlatformPaymentSetting;
use App\Models\SubscriptionCheckout;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SubscriptionPortalController extends Controller
{
    public function __invoke(
        Request $request,
        SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices,
        BuildSubscriptionPixQrCode $buildSubscriptionPixQrCode,
    ): View {
        $company = Company::query()
            ->with('subscription')
            ->whereHas('memberships', fn (Builder $query): Builder => $query
                ->where('user_id', $request->user()->id)
                ->where('active', true))
            ->firstOrFail();
        $subscription = $company->subscription;

        if ($subscription !== null) {
            $synchronizeSubscriptionInvoices->handle($subscription);
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
            'failedCheckout' => $checkouts->first(fn (SubscriptionCheckout $checkout): bool => filled($checkout->failure_message) && in_array($checkout->status, ['creating', 'rejected'], true)),
        ]);
    }
}
