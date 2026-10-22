<?php

namespace App\Actions;

use App\Models\PlatformPaymentSetting;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Services\MercadoPagoApiException;
use App\Services\MercadoPagoClient;
use App\SubscriptionStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateSubscriptionCheckout
{
    public function __construct(private MercadoPagoClient $client, private SynchronizeSubscriptionInvoices $invoices, private SynchronizeMercadoPago $synchronize) {}

    public function handle(Subscription $subscription, string $kind, string $email, string $billingCycle = 'monthly'): SubscriptionCheckout
    {
        return Cache::lock('subscription-checkout-'.$subscription->id, 90)->block(3, function () use ($subscription, $kind, $email, $billingCycle): SubscriptionCheckout {
            $subscription->refresh();
            $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();

            if (! $settings?->ready()) {
                throw ValidationException::withMessages(['payment' => 'O pagamento online ainda não está habilitado. Fale com o atendimento.']);
            }

            if (! in_array($kind, ['pix', 'card'], true) || ! $subscription->company->active
                || in_array($subscription->status, [SubscriptionStatus::Suspended, SubscriptionStatus::Cancelled], true)
                || ($kind === 'pix' && $subscription->isTrialActive())) {
                throw ValidationException::withMessages(['payment' => 'Não é possível iniciar uma cobrança nesta situação. Fale com o atendimento.']);
            }

            $this->invoices->handle($subscription);

            $targetAmount = ($kind === 'card' && $billingCycle === 'yearly')
                ? $subscription->yearlyAmountCents()
                : $subscription->monthly_amount_cents;

            $invoice = $subscription->invoices()->whereNull('paid_at')->orderBy('due_date')->orderBy('id')->first();

            if ($kind === 'card' && $invoice === null) {
                $referenceMonth = now()->startOfMonth()->toDateString();
                $invoice = $subscription->invoices()->create([
                    'reference_month' => $referenceMonth,
                    'due_date' => today()->toDateString(),
                    'amount_cents' => $targetAmount,
                    'notes' => $billingCycle === 'yearly' ? 'Assinatura plano anual antecipada.' : 'Assinatura plano mensal antecipada.',
                    'generated_automatically' => true,
                ]);
            }

            if ($invoice === null || $invoice->amount_cents <= 0 || ($kind === 'pix' && $invoice->due_date->isFuture())) {
                throw ValidationException::withMessages(['payment' => 'Não há mensalidade vencendo disponível para pagamento.']);
            }

            if ($kind === 'card' && $invoice->amount_cents !== $targetAmount) {
                $invoice->update(['amount_cents' => $targetAmount]);
            }

            if ($kind === 'card' && SubscriptionCheckout::query()->where('subscription_id', $subscription->id)->where('kind', 'pix')
                ->whereIn('status', ['creating', 'pending', 'in_process'])->exists()) {
                throw ValidationException::withMessages(['payment' => 'Consulte e cancele o Pix pendente antes de iniciar a recorrência.']);
            }

            $recurrence = SubscriptionCheckout::query()->where('subscription_id', $subscription->id)->where('kind', 'card')
                ->whereNotIn('status', ['cancelled', 'rejected'])->latest('id')->first();

            if ($recurrence !== null) {
                if ($kind === 'card' && $recurrence->environment === $settings->environment && filled($recurrence->checkout_url) && $recurrence->status === 'pending') {
                    return $recurrence;
                }
                if (! ($kind === 'card' && $recurrence->environment === $settings->environment && $recurrence->status === 'creating' && $recurrence->remote_id === null)) {
                    throw ValidationException::withMessages(['payment' => 'Cancele a recorrência existente antes de iniciar outro pagamento.']);
                }
            }

            $checkout = SubscriptionCheckout::query()->where('subscription_invoice_id', $invoice->id)
                ->where('environment', $settings->environment)->where('kind', $kind)
                ->whereIn('status', ['creating', 'pending', 'in_process'])->latest('id')->first();

            if ($checkout?->expires_at?->isPast() && $checkout->remote_id !== null) {
                throw ValidationException::withMessages(['payment' => 'Consulte o pagamento anterior antes de gerar outro QR Code.']);
            }

            if ($checkout !== null && $checkout->remote_id !== null) {
                if ($checkout->amount_cents !== $invoice->amount_cents) {
                    throw ValidationException::withMessages(['payment' => 'O valor mudou. Cancele o pagamento anterior antes de gerar outro.']);
                }

                return $checkout;
            }

            $checkout ??= SubscriptionCheckout::query()->create([
                'subscription_id' => $subscription->id,
                'subscription_invoice_id' => $invoice->id,
                'uuid' => (string) Str::uuid(),
                'environment' => $settings->environment,
                'kind' => $kind,
                'billing_cycle' => $kind === 'card' ? $billingCycle : 'monthly',
                'status' => 'creating',
                'amount_cents' => $invoice->amount_cents,
                'payer_email' => $email,
                'expires_at' => $kind === 'pix' ? now()->addDay() : null,
            ]);

            try {
                if ($kind === 'pix') {
                    $data = $this->client->request($checkout->environment, 'POST', '/v1/payments', [
                        'transaction_amount' => $checkout->amount_cents / 100,
                        'description' => 'Mensalidade GiraComanda',
                        'payment_method_id' => 'pix',
                        'payer' => ['email' => $checkout->payer_email],
                        'external_reference' => $checkout->uuid,
                        'notification_url' => $this->client->webhookUrl($checkout->environment),
                        'date_of_expiration' => $checkout->expires_at->format('Y-m-d\TH:i:s.000P'),
                    ], $checkout->uuid);
                    if (blank($data['id'] ?? null) || blank(data_get($data, 'point_of_interaction.transaction_data.qr_code'))) {
                        throw new RuntimeException('O Mercado Pago ainda não disponibilizou o QR Code. Consulte novamente.');
                    }
                    $checkout->update([
                        'remote_id' => (string) $data['id'],
                        'status' => $data['status'] ?? 'pending',
                        'pix_code' => data_get($data, 'point_of_interaction.transaction_data.qr_code'),
                        'expires_at' => $data['date_of_expiration'] ?? $checkout->expires_at,
                    ]);
                } else {
                    if ($checkout->wasRecentlyCreated === false) {
                        // The subscriptions API does not promise idempotent creation. Reconcile an ambiguous timeout, never blindly repeat POST.
                        if (! $this->synchronize->recover($checkout)) {
                            throw new RuntimeException('Nenhuma assinatura foi localizada para a tentativa anterior. A criação não foi confirmada; consulte ou encerre a tentativa local antes de iniciar outra.');
                        }
                        $checkout->refresh()->update(['failure_http_status' => null, 'failure_message' => null, 'failed_at' => null]);

                        return $checkout;
                    } else {
                        $frequency = $billingCycle === 'yearly' ? 12 : 1;
                        $reason = $billingCycle === 'yearly' ? 'GiraComanda — Assinatura Anual' : 'GiraComanda — Assinatura Mensal';

                        $data = $this->client->request($checkout->environment, 'POST', '/preapproval', [
                            'reason' => $reason,
                            'external_reference' => $checkout->uuid,
                            'payer_email' => $checkout->payer_email,
                            'auto_recurring' => [
                                'frequency' => $frequency,
                                'frequency_type' => 'months',
                                'transaction_amount' => $checkout->amount_cents / 100,
                                'currency_id' => 'BRL',
                            ],
                            'back_url' => rtrim(config('app.url'), '/').route('subscription.show', [], false),
                            'status' => 'pending',
                        ]);
                    }
                    if (blank($data['id'] ?? null)) {
                        throw new RuntimeException('A recorrência está em verificação. Tente consultar novamente.');
                    }
                    $checkout->update([
                        'remote_id' => (string) $data['id'],
                        'status' => $data['status'] ?? 'pending',
                        'checkout_url' => $this->client->checkoutUrl($data['init_point'] ?? ''),
                    ]);
                }
            } catch (MercadoPagoApiException $exception) {
                $checkout->update([
                    'status' => $checkout->wasRecentlyCreated && $exception->isDefinitiveRejection() ? 'rejected' : $checkout->status,
                    'failure_http_status' => $exception->httpStatus,
                    'failure_message' => $exception->getMessage(),
                    'failed_at' => now(),
                ]);
                throw $exception;
            }

            $checkout->update(['failure_http_status' => null, 'failure_message' => null, 'failed_at' => null]);

            return $checkout;
        });
    }
}
