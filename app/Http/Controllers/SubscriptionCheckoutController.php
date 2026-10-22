<?php

namespace App\Http\Controllers;

use App\Actions\CancelSubscriptionCheckout;
use App\Actions\CreateSubscriptionCheckout;
use App\Actions\SynchronizeMercadoPago;
use App\CompanyRole;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionCheckoutController extends Controller
{
    public function store(Request $request, CreateSubscriptionCheckout $create): RedirectResponse
    {
        $subscription = $this->subscription($request);
        $validated = $request->validate([
            'kind' => ['nullable', 'in:pix,card'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
            'payer_email' => ['required', 'email:rfc', 'max:254'],
            'recurring_consent' => ['accepted_if:kind,card'],
        ], ['recurring_consent.accepted_if' => 'Confirme a autorização da assinatura recorrente para continuar.']);

        $kind = $validated['kind'] ?? 'card';
        $billingCycle = $validated['billing_cycle'] ?? 'monthly';

        try {
            $checkout = $create->handle($subscription, $kind, $validated['payer_email'], $billingCycle);
        } catch (RuntimeException|LockTimeoutException $exception) {
            return back()->withInput(['payer_email' => $validated['payer_email']])->withErrors(['payment' => $exception instanceof LockTimeoutException ? 'Outra operação está em andamento. Aguarde e consulte novamente.' : $exception->getMessage()]);
        }
        PlatformActivityLog::record($request, 'subscription_checkout_created', 'Pagamento '.$checkout->kind.' iniciado no ambiente '.$checkout->environment.'.', $subscription->company);

        if ($checkout->kind === 'card' && filled($checkout->checkout_url) && $checkout->status === 'pending') {
            return redirect()->away($checkout->checkout_url);
        }

        return to_route('subscription.show')->with('payment_feedback', ['level' => 'info', 'message' => $checkout->kind === 'pix'
            ? 'QR Code Pix disponibilizado. Nenhum pagamento foi confirmado; aguarde a confirmação do Mercado Pago.'
            : 'Assinatura localizada no Mercado Pago. Consulte a situação do pagamento; a localização da assinatura não confirma quitação.']);
    }

    public function update(Request $request, SubscriptionCheckout $checkout, SynchronizeMercadoPago $synchronize): RedirectResponse
    {
        $this->authorizeCheckout($request, $checkout);
        $request->session()->forget(['success', 'payment_feedback']);
        try {
            $result = $synchronize->checkout($checkout);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('payment_feedback', $result);
    }

    public function destroy(Request $request, SubscriptionCheckout $checkout, CancelSubscriptionCheckout $cancel): RedirectResponse
    {
        $subscription = $this->authorizeCheckout($request, $checkout);
        $request->session()->forget(['success', 'payment_feedback']);
        try {
            $result = $cancel->handle($checkout);
        } catch (RuntimeException|LockTimeoutException $exception) {
            return back()->withErrors(['payment' => $exception instanceof LockTimeoutException ? 'Outra operação está em andamento. Tente novamente.' : $exception->getMessage()]);
        }
        PlatformActivityLog::record($request, $result['remote_cancelled'] ? 'subscription_checkout_cancelled' : 'subscription_checkout_cancel_not_confirmed', $result['message'], $subscription->company);

        return back()->with('payment_feedback', ['level' => $result['level'], 'message' => $result['message']]);
    }

    private function authorizeCheckout(Request $request, SubscriptionCheckout $checkout): Subscription
    {
        if ($request->user()?->active && $request->user()->platformAdmin()->exists()) {
            return $checkout->subscription;
        }
        $subscription = $this->subscription($request);
        abort_unless($checkout->subscription_id === $subscription->id, 404);

        return $subscription;
    }

    private function subscription(Request $request): Subscription
    {
        abort_unless($request->user()?->active, 403);
        $company = Company::query()->whereHas('memberships', fn (Builder $query): Builder => $query
            ->where('user_id', $request->user()->id)->where('active', true)->where('role', CompanyRole::CompanyAdmin))->first();
        abort_if($company === null, 403);

        return $company->subscription()->firstOrFail();
    }
}
