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
            'kind' => ['required', 'in:pix,card'], 'payer_email' => ['required', 'email:rfc', 'max:254'],
            'recurring_consent' => ['accepted_if:kind,card'],
        ], ['recurring_consent.accepted_if' => 'Confirme a autorização da assinatura recorrente para continuar.']);

        try {
            $checkout = $create->handle($subscription, $validated['kind'], $validated['payer_email']);
        } catch (RuntimeException|LockTimeoutException $exception) {
            return back()->withErrors(['payment' => $exception instanceof LockTimeoutException ? 'Outra operação está em andamento. Aguarde e consulte novamente.' : $exception->getMessage()]);
        }
        PlatformActivityLog::record($request, 'subscription_checkout_created', 'Pagamento '.$checkout->kind.' iniciado no ambiente '.$checkout->environment.'.', $subscription->company);

        return $checkout->kind === 'card' ? redirect()->away($checkout->checkout_url) : to_route('subscription.show')->with('success', 'QR Code Pix gerado. A liberação ocorre após a confirmação do pagamento.');
    }

    public function update(Request $request, SubscriptionCheckout $checkout, SynchronizeMercadoPago $synchronize): RedirectResponse
    {
        $this->authorizeCheckout($request, $checkout);
        try {
            $synchronize->checkout($checkout);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('success', 'Situação consultada no Mercado Pago.');
    }

    public function destroy(Request $request, SubscriptionCheckout $checkout, CancelSubscriptionCheckout $cancel): RedirectResponse
    {
        $subscription = $this->authorizeCheckout($request, $checkout);
        try {
            $cancel->handle($checkout);
        } catch (RuntimeException|LockTimeoutException $exception) {
            return back()->withErrors(['payment' => $exception instanceof LockTimeoutException ? 'Outra operação está em andamento. Tente novamente.' : $exception->getMessage()]);
        }
        PlatformActivityLog::record($request, 'subscription_checkout_cancelled', 'Cancelamento de '.$checkout->kind.' confirmado no Mercado Pago. Período já pago preservado.', $subscription->company);

        return back()->with('success', 'Cancelamento confirmado. O período já pago continua disponível.');
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
