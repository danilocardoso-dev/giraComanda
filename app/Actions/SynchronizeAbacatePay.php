<?php

namespace App\Actions;

use App\Models\PlatformActivityLog;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionGatewayPayment;
use App\Models\SubscriptionInvoice;
use App\Services\AbacatePayApiException;
use App\Services\AbacatePayClient;
use App\SubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SynchronizeAbacatePay
{
    /** @var array<string, string> */
    private const TRANSPARENT_STATUS = [
        'PENDING' => 'pending', 'PAID' => 'approved', 'APPROVED' => 'approved', 'EXPIRED' => 'cancelled', 'CANCELLED' => 'cancelled',
        'REFUNDED' => 'refunded', 'UNDER_DISPUTE' => 'charged_back', 'FAILED' => 'rejected',
    ];

    public function __construct(private AbacatePayClient $client, private SettleSubscriptionInvoice $settle) {}

    /** @return array{level: string, message: string} */
    public function checkout(SubscriptionCheckout $checkout): array
    {
        $checkout->update(['last_attempted_at' => now()]);
        if ($checkout->status === 'rejected' && $checkout->remote_id === null) {
            throw new RuntimeException($checkout->failure_message ?? 'A criação foi recusada. Nenhuma assinatura ou pagamento foi confirmado.');
        }
        if ($checkout->status === 'cancelled' && $checkout->remote_id === null) {
            return ['level' => 'info', 'message' => 'Esta tentativa foi encerrada apenas no GiraComanda. Não houve confirmação de criação ou cancelamento de cobrança no AbacatePay.'];
        }
        if ($checkout->remote_id === null) {
            if (! $this->recover($checkout)) {
                throw new RuntimeException('Nenhuma '.($checkout->kind === 'card' ? 'assinatura' : 'cobrança Pix').' foi localizada no AbacatePay para esta tentativa. A criação e o pagamento não foram confirmados.');
            }

            return $this->checkout($checkout->refresh());
        }
        if ($checkout->kind === 'pix') {
            $data = $this->client->request($checkout->environment, 'GET', '/transparents/get', ['id' => $checkout->remote_id]);
            $this->transparent($checkout->environment, $data, $checkout);
        } else {
            $this->subscriptionCheckout($checkout);
        }
        $checkout->update(['last_synced_at' => now()]);

        return $this->feedback($checkout->refresh());
    }

    public function recover(SubscriptionCheckout $checkout): bool
    {
        // Pix charges cannot be searched by our reference, so only subscription checkouts are recoverable.
        if ($checkout->kind !== 'card') {
            return false;
        }

        try {
            $data = $this->client->request($checkout->environment, 'GET', '/subscriptions/checkouts/get', ['externalId' => $checkout->uuid]);
        } catch (AbacatePayApiException $exception) {
            if ($exception->httpStatus === 404) {
                return false;
            }
            throw $exception;
        }
        if (blank($data['id'] ?? null) || ($data['externalId'] ?? null) !== $checkout->uuid) {
            return false;
        }
        $this->applyCheckoutData($checkout, $data);

        return true;
    }

    /**
     * Processes an AbacatePay webhook payload. The caller has already authenticated the request.
     *
     * @param  array<string, mixed>  $payload
     */
    public function event(string $environment, array $payload): void
    {
        $devMode = $payload['devMode'] ?? null;
        if (($environment === 'production' && $devMode === true) || ($environment === 'test' && $devMode === false)) {
            return;
        }
        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        match ($event) {
            'transparent.completed', 'transparent.refunded', 'transparent.disputed', 'transparent.lost' => $this->transparentEvent($environment, $data),
            'checkout.completed' => $this->checkoutCompletedEvent($environment, $data),
            'subscription.completed' => $this->subscriptionPaymentEvent($environment, $data, true),
            'subscription.renewed' => $this->subscriptionPaymentEvent($environment, $data, false),
            'subscription.cancelled' => $this->subscriptionCancelledEvent($environment, $data),
            'subscription.payment_failed' => Log::warning('Cobrança recorrente AbacatePay falhou.', ['environment' => $environment, 'subscription_id' => data_get($data, 'subscription.id'), 'retry' => data_get($data, 'retryNumber')]),
            default => null,
        };
    }

    private function transparentEvent(string $environment, array $data): void
    {
        $id = data_get($data, 'transparent.id');
        if (! is_string($id) || $id === '') {
            return;
        }
        if (! SubscriptionCheckout::query()->where('environment', $environment)->where('kind', 'pix')->where('remote_id', $id)->exists()) {
            return;
        }
        // The charge is re-read from the API: the webhook only tells us where to look.
        $charge = $this->client->request($environment, 'GET', '/transparents/get', ['id' => $id]);
        $this->transparent($environment, $charge, null);
    }

    /**
     * First payment of a subscription checkout. Used when it arrives before (or without) subscription.completed.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkoutCompletedEvent(string $environment, array $data): void
    {
        $billId = data_get($data, 'checkout.id');
        if (data_get($data, 'checkout.frequency') !== 'SUBSCRIPTION' || data_get($data, 'checkout.status') !== 'PAID' || ! is_string($billId)) {
            return;
        }
        $checkout = $this->findRecurrence($environment, null, $billId);
        if ($checkout === null) {
            Log::warning('Evento AbacatePay sem checkout correspondente.', ['environment' => $environment, 'event' => 'checkout.completed', 'checkout_id' => $billId]);

            return;
        }
        if ($checkout->payments()->whereNotNull('applied_at')->exists()) {
            return;
        }
        $checkout->update(['status' => 'authorized', 'cancelled_at' => null]);

        $amount = $this->cents(data_get($data, 'checkout.paidAmount') ?? data_get($data, 'checkout.amount') ?? 0);
        $paidAt = Carbon::parse(data_get($data, 'checkout.updatedAt') ?? now());
        $this->applyPayment($environment, $checkout, $billId, $amount, 'approved', $paidAt);
    }

    /** @param array<string, mixed> $data */
    private function subscriptionPaymentEvent(string $environment, array $data, bool $first): void
    {
        $subscriptionId = data_get($data, 'subscription.id');
        $checkout = $this->findRecurrence($environment, $subscriptionId, data_get($data, 'checkout.id'));
        $paymentId = data_get($data, 'payment.id');
        if ($checkout === null || ! is_string($subscriptionId) || ! is_string($paymentId) || $paymentId === '') {
            Log::warning('Evento AbacatePay de assinatura não pôde ser aplicado.', ['environment' => $environment, 'subscription_id' => $subscriptionId, 'checkout_id' => data_get($data, 'checkout.id'), 'payment_id' => $paymentId, 'checkout_found' => $checkout !== null]);

            return;
        }
        if ($checkout->provider_subscription_id !== null && $checkout->provider_subscription_id !== $subscriptionId) {
            throw new RuntimeException('Identificação da recorrência divergente.');
        }
        $checkout->update(['provider_subscription_id' => $subscriptionId, 'status' => 'authorized', 'cancelled_at' => null]);

        // The first payment may already have been applied from checkout.completed, which identifies it by the bill id.
        if ($first && $checkout->payments()->where('remote_id', $checkout->remote_id)->whereNotNull('applied_at')->exists()) {
            return;
        }

        $amount = $this->cents(data_get($data, 'payment.paidAmount') ?? data_get($data, 'payment.amount') ?? 0);
        $paidAt = Carbon::parse(data_get($data, 'payment.updatedAt') ?? data_get($data, 'payment.createdAt') ?? now());
        $status = in_array(data_get($data, 'payment.status'), ['PAID', 'APPROVED'], true) ? 'approved' : 'pending';
        $this->applyPayment($environment, $checkout, $paymentId, $amount, $status, $paidAt);
    }

    private function subscriptionCancelledEvent(string $environment, array $data): void
    {
        $checkout = $this->findRecurrence($environment, data_get($data, 'subscription.id'), null);
        if ($checkout === null || in_array($checkout->status, ['cancelled', 'rejected'], true)) {
            return;
        }
        $checkout->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    private function findRecurrence(string $environment, mixed $subscriptionId, mixed $checkoutId): ?SubscriptionCheckout
    {
        $query = SubscriptionCheckout::query()->where('environment', $environment)->where('kind', 'card');

        if (is_string($subscriptionId) && $subscriptionId !== '') {
            $found = (clone $query)->where('provider_subscription_id', $subscriptionId)->first();
            if ($found !== null) {
                return $found;
            }
        }

        return is_string($checkoutId) && $checkoutId !== '' ? $query->where('remote_id', $checkoutId)->first() : null;
    }

    /** @param array<int|string, mixed> $data */
    private function subscriptionCheckout(SubscriptionCheckout $checkout): void
    {
        $data = $this->client->request($checkout->environment, 'GET', '/subscriptions/checkouts/get', ['id' => $checkout->remote_id]);
        if ((string) ($data['id'] ?? '') !== $checkout->remote_id) {
            throw new RuntimeException('Identificação da recorrência divergente.');
        }
        $this->applyCheckoutData($checkout, $data);
        $checkout->refresh();

        if ($checkout->status === 'authorized' && $checkout->provider_subscription_id === null) {
            $list = $this->client->request($checkout->environment, 'GET', '/subscriptions/list', ['checkoutId' => $checkout->remote_id]);
            $first = array_is_list($list) ? ($list[0] ?? null) : ($list['subscriptions'][0] ?? ($list['items'][0] ?? null));
            if (is_array($first) && is_string($first['id'] ?? null)) {
                $checkout->update(['provider_subscription_id' => $first['id']]);
            }
        }
        if ($checkout->provider_subscription_id !== null && $checkout->status === 'authorized') {
            $subscription = $this->client->request($checkout->environment, 'GET', '/subscriptions/get', ['id' => $checkout->provider_subscription_id]);
            if (($subscription['status'] ?? '') === 'CANCELLED') {
                $checkout->update(['status' => 'cancelled', 'cancelled_at' => $checkout->cancelled_at ?? now()]);
            }
        }
        // Renewals and first payments are applied from webhooks; consulting never pays an invoice by itself.
    }

    /** @param array<int|string, mixed> $data */
    private function applyCheckoutData(SubscriptionCheckout $checkout, array $data): void
    {
        if (($data['externalId'] ?? null) !== $checkout->uuid
            || ($checkout->remote_id !== null && $checkout->remote_id !== (string) ($data['id'] ?? ''))
            || $this->cents($data['amount'] ?? 0) !== $checkout->amount_cents) {
            throw new RuntimeException('Condições da recorrência divergentes. Revisão administrativa necessária.');
        }
        $status = match ($data['status'] ?? '') {
            'PENDING' => 'pending',
            'PAID' => 'authorized',
            'EXPIRED', 'CANCELLED' => 'cancelled',
            'REFUNDED' => 'refunded',
            default => throw new RuntimeException('Situação da recorrência não reconhecida.'),
        };
        // A subscription cancelled after payment keeps the checkout in PAID, so never reopen a cancelled recurrence.
        if ($checkout->status === 'cancelled' && $status === 'authorized' && $checkout->remote_id !== null) {
            $status = 'cancelled';
        }
        $checkout->update([
            'remote_id' => (string) $data['id'], 'status' => $status,
            'checkout_url' => isset($data['url']) ? $this->client->checkoutUrl($data['url']) : $checkout->checkout_url,
            'cancelled_at' => $status === 'cancelled' ? ($checkout->cancelled_at ?? now()) : null,
        ]);
    }

    /** @param array<int|string, mixed> $data */
    private function transparent(string $environment, array $data, ?SubscriptionCheckout $expected): void
    {
        $id = (string) ($data['id'] ?? '');
        $checkout = $expected ?? SubscriptionCheckout::query()->where('environment', $environment)->where('kind', 'pix')->where('remote_id', $id)->first();
        if ($checkout === null) {
            return;
        }
        if ($id === '' || $checkout->remote_id !== $id || $checkout->kind !== 'pix') {
            throw new RuntimeException('Identificação ou ambiente do pagamento divergente.');
        }
        $status = self::TRANSPARENT_STATUS[$data['status'] ?? ''] ?? throw new RuntimeException('Situação do pagamento não reconhecida.');
        $paidAt = Carbon::parse($data['updatedAt'] ?? now());
        $this->applyPayment($environment, $checkout, $id, $this->cents($data['amount'] ?? 0), $status, $paidAt);
    }

    private function applyPayment(string $environment, SubscriptionCheckout $checkout, string $id, int $amount, string $status, Carbon $paidAt): void
    {
        DB::transaction(function () use ($checkout, $environment, $id, $amount, $status, $paidAt): void {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($checkout->subscription_id);
            $checkout = SubscriptionCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            $payment = SubscriptionGatewayPayment::query()->firstOrCreate(['environment' => $environment, 'remote_id' => $id], [
                'subscription_checkout_id' => $checkout->id, 'status' => $status, 'amount_cents' => $amount,
            ]);
            if ($payment->subscription_checkout_id !== $checkout->id) {
                throw new RuntimeException('Pagamento já vinculado a outra assinatura.');
            }
            $payment->update(['status' => $status]);
            if ($checkout->kind === 'pix') {
                $checkout->update(['status' => $status, 'last_synced_at' => now()]);
            }
            if ($payment->applied_at !== null) {
                if (in_array($status, ['refunded', 'charged_back'], true)) {
                    $this->review($payment, $subscription, 'refund_or_chargeback');
                }

                return;
            }
            $payment->update(['amount_cents' => $amount]);
            if ($status === 'refunded' || $status === 'charged_back') {
                $this->review($payment, $subscription, 'refund_or_chargeback');

                return;
            }
            if ($status !== 'approved') {
                return;
            }
            if ($amount !== $checkout->amount_cents) {
                $this->review($payment, $subscription, 'amount_currency_or_method_mismatch');

                return;
            }
            $payment->update(['approved_at' => $paidAt]);
            $invoice = SubscriptionInvoice::query()->lockForUpdate()->findOrFail($checkout->subscription_invoice_id);

            if ($checkout->kind === 'card' && $subscription->status !== SubscriptionStatus::Trial && ! $invoice->reference_month->isSameMonth($paidAt)) {
                $invoice = $subscription->invoices()->firstOrCreate(['reference_month' => $paidAt->copy()->startOfMonth()->toDateString()], [
                    'due_date' => $paidAt->toDateString(), 'amount_cents' => $checkout->amount_cents,
                    'generated_automatically' => true, 'notes' => $checkout->billing_cycle === 'yearly' ? 'Assinatura anual recorrente AbacatePay.' : 'Mensalidade recorrente AbacatePay.',
                ]);
            }
            $payment->update(['subscription_invoice_id' => $invoice->id]);
            if ($subscription->status === SubscriptionStatus::Trial && $invoice->generated_automatically
                && ! $invoice->reference_month->isSameMonth($paidAt)
                && $subscription->invoices()->whereDate('reference_month', $paidAt->copy()->startOfMonth())->whereKeyNot($invoice->id)->exists()) {
                $this->review($payment, $subscription, 'conflicting_billing_cycle');

                return;
            }
            if ($invoice->paid_at !== null || $invoice->amount_cents !== $amount) {
                $this->review($payment, $subscription, 'invoice_already_paid_or_changed');

                return;
            }
            $reactivate = ! in_array($subscription->status, [SubscriptionStatus::Suspended, SubscriptionStatus::Cancelled], true)
                && $subscription->company->active;
            $this->settle->handle($subscription, $invoice, $paidAt, $checkout->kind === 'pix' ? 'pix' : 'credit_card', $reactivate, true, $checkout->billing_cycle);
            $payment->update(['applied_at' => now(), 'review_reason' => $reactivate ? null : 'access_kept_blocked_by_admin']);
            PlatformActivityLog::query()->create(['company_id' => $subscription->company_id, 'action' => 'abacatepay_payment_applied',
                'description' => 'Pagamento AbacatePay '.$id.' confirmado: R$ '.number_format($amount / 100, 2, ',', '.').($reactivate ? ($checkout->billing_cycle === 'yearly' ? '. Acesso anual liberado.' : '. Acesso mensal liberado.') : '. Bloqueio administrativo preservado.')]);
        });
    }

    private function review(SubscriptionGatewayPayment $payment, Subscription $subscription, string $reason): void
    {
        if ($payment->review_reason === $reason) {
            return;
        }
        $payment->update(['review_reason' => $reason]);
        PlatformActivityLog::query()->create(['company_id' => $subscription->company_id, 'action' => 'abacatepay_payment_review',
            'description' => 'Pagamento AbacatePay '.$payment->remote_id.' requer revisão: '.$reason.'.']);
    }

    private function cents(mixed $amount): int
    {
        if (! is_numeric($amount) || (float) $amount < 0) {
            throw new RuntimeException('Valor de pagamento inválido.');
        }

        return (int) round((float) $amount);
    }

    /** @return array{level: string, message: string} */
    private function feedback(SubscriptionCheckout $checkout): array
    {
        $payment = $checkout->payments()->with('invoice')->latest('id')->first();
        if ($payment?->review_reason) {
            return ['level' => 'warning', 'message' => 'O pagamento '.$payment->remote_id.' exige revisão administrativa. Esta consulta não confirma liberação do acesso. Confira o histórico no painel SaaS.'];
        }
        if ($payment?->status === 'approved' && $payment->applied_at && $payment->invoice?->paid_at) {
            return ['level' => 'success', 'message' => 'Pagamento '.$payment->remote_id.' confirmado: mensalidade de '.$payment->invoice->reference_month->format('m/Y').' quitada.'.($checkout->subscription->allowsAccess() ? ' Acesso liberado.' : ' O acesso continua bloqueado; fale com o atendimento.')];
        }
        $message = match ($checkout->status) {
            'pending' => $checkout->kind === 'card' ? 'Assinatura criada, aguardando o pagamento no AbacatePay. Nenhum pagamento foi confirmado.' : 'Pix aguardando pagamento. Nenhuma baixa foi confirmada.',
            'authorized' => 'Assinatura paga no AbacatePay. A confirmação do pagamento chega por notificação; o acesso não é liberado por esta consulta.',
            'cancelled' => 'O AbacatePay informa que esta cobrança ou recorrência está cancelada ou expirada. Isso não é confirmação de pagamento.',
            'rejected' => 'O AbacatePay recusou esta tentativa. Nenhum pagamento foi confirmado.',
            'refunded', 'charged_back' => 'O AbacatePay informa estorno ou contestação. O pagamento precisa de revisão administrativa.',
            default => 'Não foi possível confirmar pagamento ou liberação de acesso. Consulte o atendimento.',
        };

        return ['level' => in_array($checkout->status, ['rejected', 'refunded', 'charged_back'], true) ? 'warning' : 'info', 'message' => $message];
    }
}
