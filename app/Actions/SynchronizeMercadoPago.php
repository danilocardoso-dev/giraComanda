<?php

namespace App\Actions;

use App\Models\PlatformActivityLog;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionGatewayPayment;
use App\Models\SubscriptionInvoice;
use App\Services\MercadoPagoClient;
use App\SubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SynchronizeMercadoPago
{
    public function __construct(private MercadoPagoClient $client, private SettleSubscriptionInvoice $settle) {}

    public function checkout(SubscriptionCheckout $checkout): void
    {
        if ($checkout->remote_id === null) {
            if ($checkout->kind === 'card') {
                $found = $this->client->request($checkout->environment, 'GET', '/preapproval/search', ['external_reference' => $checkout->uuid]);
                $data = collect($found['results'] ?? [])->first(fn (array $row): bool => ($row['external_reference'] ?? null) === $checkout->uuid);
                if ($data !== null) {
                    $this->recurrence($checkout->environment, (string) $data['id']);
                }
            } else {
                $found = $this->client->request($checkout->environment, 'GET', '/v1/payments/search', ['external_reference' => $checkout->uuid]);
                $data = collect($found['results'] ?? [])->first(fn (array $row): bool => ($row['external_reference'] ?? null) === $checkout->uuid);
                if ($data !== null) {
                    $checkout->update(['remote_id' => (string) $data['id']]);
                    $this->payment($checkout->environment, (string) $data['id']);
                }
            }

            return;
        }
        if ($checkout->kind === 'pix') {
            $this->payment($checkout->environment, $checkout->remote_id);
        } else {
            $this->recurrence($checkout->environment, $checkout->remote_id);
            $deadline = microtime(true) + 35;
            $offset = (int) $checkout->reconciliation_offset;
            $page = $this->client->request($checkout->environment, 'GET', '/authorized_payments/search', [
                'preapproval_id' => $checkout->remote_id, 'limit' => 20, 'offset' => $offset,
            ]);
            foreach ($page['results'] ?? [] as $item) {
                $known = $checkout->payments()->where('remote_id', (string) data_get($item, 'payment.id'))->first();
                if ($known === null || $known->status !== data_get($item, 'payment.status')
                    || $known->applied_at === null || $known->updated_at->lt(Carbon::parse($item['last_modified'] ?? now()))) {
                    $this->authorizedPayment($checkout->environment, (string) $item['id']);
                }
                $offset++;
                $checkout->update(['reconciliation_offset' => $offset >= (int) data_get($page, 'paging.total', 0) ? 0 : $offset]);
                if (microtime(true) >= $deadline) {
                    break;
                }
            }
            if (empty($page['results'])) {
                $checkout->update(['reconciliation_offset' => 0]);
            }
        }
        $checkout->update(['last_synced_at' => now()]);
    }

    public function recurrence(string $environment, string $id): void
    {
        $data = $this->client->request($environment, 'GET', '/preapproval/'.rawurlencode($id));
        $checkout = SubscriptionCheckout::query()->where('environment', $environment)->where('kind', 'card')
            ->where('uuid', $data['external_reference'] ?? '')->first();

        if ($checkout === null) {
            return;
        }
        if (($checkout->remote_id !== null && $checkout->remote_id !== $id) || (string) ($data['id'] ?? '') !== $id) {
            throw new RuntimeException('Identificação da recorrência divergente.');
        }
        $auto = $data['auto_recurring'] ?? [];
        if (($auto['currency_id'] ?? null) !== 'BRL' || $this->cents($auto['transaction_amount'] ?? 0) !== $checkout->amount_cents
            || (int) ($auto['frequency'] ?? 0) !== 1 || ($auto['frequency_type'] ?? '') !== 'months') {
            throw new RuntimeException('Condições da recorrência divergentes. Revisão administrativa necessária.');
        }
        $status = $data['status'] ?? '';
        if (! in_array($status, ['pending', 'authorized', 'paused', 'cancelled'], true)) {
            throw new RuntimeException('Situação da recorrência não reconhecida.');
        }
        $checkout->update(['remote_id' => $id, 'status' => $status,
            'checkout_url' => isset($data['init_point']) ? $this->client->checkoutUrl($data['init_point']) : $checkout->checkout_url,
            'cancelled_at' => $status === 'cancelled' ? ($checkout->cancelled_at ?? now()) : null,
            'last_synced_at' => now()]);
        // Authorization/cancellation never pays an invoice or removes an already paid period.
    }

    public function authorizedPayment(string $environment, string $id): void
    {
        $data = $this->client->request($environment, 'GET', '/authorized_payments/'.rawurlencode($id));
        $checkout = SubscriptionCheckout::query()->where('environment', $environment)->where('kind', 'card')
            ->where('remote_id', $data['preapproval_id'] ?? '')->first();
        if ($checkout === null || blank(data_get($data, 'payment.id'))) {
            return;
        }
        if (($data['external_reference'] ?? null) !== $checkout->uuid || (string) ($data['id'] ?? '') !== $id) {
            throw new RuntimeException('Identificação da cobrança recorrente divergente.');
        }
        $this->payment($environment, (string) $data['payment']['id'], $checkout, $data['debit_date'] ?? null);
    }

    public function payment(string $environment, string $id, ?SubscriptionCheckout $recurrence = null, ?string $debitDate = null): void
    {
        $data = $this->client->request($environment, 'GET', '/v1/payments/'.rawurlencode($id));
        $checkout = $recurrence ?? SubscriptionCheckout::query()->where('environment', $environment)
            ->where('uuid', $data['external_reference'] ?? '')->first();

        if ($checkout === null) {
            return;
        }
        if ($checkout->kind === 'card' && $recurrence === null) {
            // Resolve the authorized invoice to verify the preapproval and billing cycle.
            $found = $this->client->request($environment, 'GET', '/authorized_payments/search', ['payment_id' => $id]);
            foreach ($found['results'] ?? [] as $row) {
                if ((string) data_get($row, 'payment.id') === $id) {
                    $this->authorizedPayment($environment, (string) $row['id']);

                    return;
                }
            }
            throw new RuntimeException('A fatura recorrente ainda não está disponível para consulta.');
        }
        if ((string) ($data['id'] ?? '') !== $id || ($data['external_reference'] ?? null) !== $checkout->uuid
            || ($checkout->kind === 'pix' && $checkout->remote_id !== $id)
            || ! is_bool($data['live_mode'] ?? null) || $data['live_mode'] !== ($environment === 'production')) {
            throw new RuntimeException('Identificação ou ambiente do pagamento divergente.');
        }

        DB::transaction(function () use ($checkout, $data, $environment, $id, $debitDate): void {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($checkout->subscription_id);
            $checkout = SubscriptionCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            $amount = $this->cents($data['transaction_amount'] ?? 0);
            $status = $data['status'] ?? 'unknown';
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
                if (in_array($status, ['refunded', 'charged_back'], true) || (float) ($data['transaction_amount_refunded'] ?? 0) > 0) {
                    $this->review($payment, $subscription, 'refund_or_chargeback');
                }

                return;
            }
            $payment->update(['amount_cents' => $amount]);
            if ($status !== 'approved') {
                return;
            }
            if (($data['currency_id'] ?? '') !== 'BRL' || $amount !== $checkout->amount_cents
                || ($checkout->kind === 'pix' && ($data['payment_method_id'] ?? '') !== 'pix')
                || ($checkout->kind === 'card' && ($data['payment_type_id'] ?? '') !== 'credit_card')
                || (float) ($data['transaction_amount_refunded'] ?? 0) > 0) {
                $this->review($payment, $subscription, 'amount_currency_or_method_mismatch');

                return;
            }
            $approvedAt = Carbon::parse($data['date_approved'] ?? throw new RuntimeException('Data de aprovação ausente.'));
            $payment->update(['approved_at' => $approvedAt]);
            if ($environment === 'test') {
                $payment->update(['review_reason' => 'test_payment_not_applied']);

                return;
            }
            $invoice = SubscriptionInvoice::query()->lockForUpdate()->findOrFail($checkout->subscription_invoice_id);

            if ($checkout->kind === 'card') {
                $cycleDate = Carbon::parse($debitDate ?? throw new RuntimeException('Competência da cobrança ausente.'));
                if ($subscription->status !== SubscriptionStatus::Trial && ! $invoice->reference_month->isSameMonth($cycleDate)) {
                    $invoice = $subscription->invoices()->firstOrCreate(['reference_month' => $cycleDate->copy()->startOfMonth()->toDateString()], [
                        'due_date' => $cycleDate->toDateString(), 'amount_cents' => $checkout->amount_cents,
                        'generated_automatically' => true, 'notes' => 'Mensalidade recorrente Mercado Pago.',
                    ]);
                }
            }
            $payment->update(['subscription_invoice_id' => $invoice->id]);
            if ($subscription->status === SubscriptionStatus::Trial && $invoice->generated_automatically
                && ! $invoice->reference_month->isSameMonth($approvedAt)
                && $subscription->invoices()->whereDate('reference_month', $approvedAt->copy()->startOfMonth())->whereKeyNot($invoice->id)->exists()) {
                $this->review($payment, $subscription, 'conflicting_billing_cycle');

                return;
            }
            if ($invoice->paid_at !== null || $invoice->amount_cents !== $amount) {
                $this->review($payment, $subscription, 'invoice_already_paid_or_changed');

                return;
            }
            $reactivate = ! in_array($subscription->status, [SubscriptionStatus::Suspended, SubscriptionStatus::Cancelled], true)
                && $subscription->company->active;
            $this->settle->handle($subscription, $invoice, $approvedAt, $checkout->kind === 'pix' ? 'pix' : 'credit_card', $reactivate, true);
            $payment->update(['applied_at' => now(), 'review_reason' => $reactivate ? null : 'access_kept_blocked_by_admin']);
            PlatformActivityLog::query()->create(['company_id' => $subscription->company_id, 'action' => 'mercado_pago_payment_applied',
                'description' => 'Pagamento Mercado Pago '.$id.' confirmado: R$ '.number_format($amount / 100, 2, ',', '.').($reactivate ? '. Acesso mensal liberado.' : '. Bloqueio administrativo preservado.')]);
        });
    }

    private function review(SubscriptionGatewayPayment $payment, Subscription $subscription, string $reason): void
    {
        if ($payment->review_reason === $reason) {
            return;
        }
        $payment->update(['review_reason' => $reason]);
        PlatformActivityLog::query()->create(['company_id' => $subscription->company_id, 'action' => 'mercado_pago_payment_review',
            'description' => 'Pagamento Mercado Pago '.$payment->remote_id.' requer revisão: '.$reason.'.']);
    }

    private function cents(mixed $amount): int
    {
        if (! is_numeric($amount) || (float) $amount < 0) {
            throw new RuntimeException('Valor de pagamento inválido.');
        }

        return (int) round((float) $amount * 100);
    }
}
