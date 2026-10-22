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

    /** @return array{level: string, message: string} */
    public function checkout(SubscriptionCheckout $checkout): array
    {
        $checkout->update(['last_attempted_at' => now()]);
        if ($checkout->status === 'rejected' && $checkout->remote_id === null) {
            throw new RuntimeException($checkout->failure_message ?? 'A criação foi recusada. Nenhuma assinatura ou pagamento foi confirmado.');
        }
        if ($checkout->status === 'cancelled' && $checkout->remote_id === null) {
            return ['level' => 'info', 'message' => 'Esta tentativa foi encerrada apenas no GiraComanda. Não houve confirmação de criação ou cancelamento de cobrança no Mercado Pago.'];
        }
        if ($checkout->remote_id === null) {
            if (! $this->recover($checkout)) {
                throw new RuntimeException('Nenhuma '.($checkout->kind === 'card' ? 'assinatura' : 'cobrança Pix').' foi localizada no Mercado Pago para esta tentativa. A criação e o pagamento não foram confirmados.');
            }

            return $this->checkout($checkout->refresh());
        }
        if ($checkout->kind === 'pix') {
            $this->payment($checkout->environment, $checkout->remote_id, $checkout);
        } else {
            $this->recurrence($checkout->environment, $checkout->remote_id, $checkout);
            $deadline = microtime(true) + 35;
            $offset = (int) $checkout->reconciliation_offset;
            $page = $this->client->request($checkout->environment, 'GET', '/authorized_payments/search', [
                'preapproval_id' => $checkout->remote_id,
            ]);
            if (! isset($page['results']) || ! is_array($page['results'])) {
                throw new RuntimeException('O Mercado Pago retornou uma consulta de pagamentos incompleta. Nenhum resultado de pagamento pôde ser confirmado.');
            }
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

        return $this->feedback($checkout->refresh());
    }

    public function recover(SubscriptionCheckout $checkout): bool
    {
        $path = $checkout->kind === 'card' ? '/preapproval/search' : '/v1/payments/search';
        $found = $this->client->request($checkout->environment, 'GET', $path, ['external_reference' => $checkout->uuid]);
        $data = $this->findCheckout($found, $checkout);
        if ($data === null) {
            return false;
        }
        if ($checkout->kind === 'card') {
            $this->recurrence($checkout->environment, (string) $data['id'], $checkout);
        } else {
            $checkout->update(['remote_id' => (string) $data['id']]);
            $this->payment($checkout->environment, (string) $data['id'], $checkout);
        }

        return true;
    }

    public function recurrence(string $environment, string $id, ?SubscriptionCheckout $expected = null): void
    {
        $data = $this->client->request($environment, 'GET', '/preapproval/'.rawurlencode($id));
        $checkout = $expected ?? SubscriptionCheckout::query()->where('environment', $environment)->where('kind', 'card')
            ->where('uuid', $data['external_reference'] ?? '')->first();

        if ($checkout === null) {
            return;
        }
        if (($checkout->remote_id !== null && $checkout->remote_id !== $id) || (string) ($data['id'] ?? '') !== $id
            || ($data['external_reference'] ?? null) !== $checkout->uuid) {
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
        ]);
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
        if ((filled($data['external_reference'] ?? null) && $data['external_reference'] !== $checkout->uuid) || (string) ($data['id'] ?? '') !== $id) {
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
        $externalRef = $data['external_reference'] ?? null;
        if ((string) ($data['id'] ?? '') !== $id
            || ($recurrence === null && $externalRef !== $checkout->uuid)
            || ($recurrence !== null && filled($externalRef) && $externalRef !== $checkout->uuid)
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
                || ($checkout->kind === 'card' && ! in_array($data['payment_type_id'] ?? '', ['credit_card', 'debit_card', 'account_money', 'prepaid_card'], true))
                || (float) ($data['transaction_amount_refunded'] ?? 0) > 0) {
                $this->review($payment, $subscription, 'amount_currency_or_method_mismatch');

                return;
            }
            $approvedAt = Carbon::parse($data['date_approved'] ?? throw new RuntimeException('Data de aprovação ausente.'));
            $payment->update(['approved_at' => $approvedAt]);
            if ($environment === 'test' && ! config('giracomanda.subscription.allow_test_settle', false)) {
                $payment->update(['review_reason' => 'test_payment_not_applied']);

                return;
            }
            $invoice = SubscriptionInvoice::query()->lockForUpdate()->findOrFail($checkout->subscription_invoice_id);

            if ($checkout->kind === 'card') {
                $cycleDate = Carbon::parse($debitDate ?? $data['date_approved'] ?? $data['date_created'] ?? now());
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

    /** @return array<string, mixed>|null */
    private function findCheckout(array $response, SubscriptionCheckout $checkout): ?array
    {
        if (! isset($response['results']) || ! is_array($response['results'])) {
            throw new RuntimeException('O Mercado Pago retornou uma busca incompleta. Não foi possível confirmar a criação.');
        }
        $matches = collect($response['results'])->filter(fn ($row): bool => is_array($row) && ($row['external_reference'] ?? null) === $checkout->uuid);
        if ($matches->count() > 1) {
            throw new RuntimeException('Mais de um registro foi localizado para esta tentativa. Revisão administrativa necessária.');
        }
        $match = $matches->first();
        if ($match !== null && (! is_scalar($match['id'] ?? null) || blank($match['id']))) {
            throw new RuntimeException('O Mercado Pago retornou um registro sem identificação. A criação não pôde ser confirmada.');
        }

        return $match;
    }

    /** @return array{level: string, message: string} */
    private function feedback(SubscriptionCheckout $checkout): array
    {
        $payment = $checkout->payments()->with('invoice')->latest('id')->first();
        if ($payment?->status === 'approved' && $payment->review_reason === 'test_payment_not_applied') {
            return ['level' => 'info', 'message' => 'Pagamento de teste confirmado no Mercado Pago. É uma simulação: nenhuma mensalidade real foi quitada e o acesso não foi liberado.'];
        }
        if ($payment?->review_reason) {
            return ['level' => 'warning', 'message' => 'O pagamento '.$payment->remote_id.' exige revisão administrativa. Esta consulta não confirma liberação do acesso. Confira o histórico no painel SaaS.'];
        }
        if ($payment?->status === 'approved' && $payment->applied_at && $payment->invoice?->paid_at) {
            return ['level' => 'success', 'message' => 'Pagamento '.$payment->remote_id.' confirmado: mensalidade de '.$payment->invoice->reference_month->format('m/Y').' quitada.'.($checkout->subscription->allowsAccess() ? ' Acesso liberado.' : ' O acesso continua bloqueado; fale com o atendimento.')];
        }
        $message = match ($checkout->status) {
            'pending' => $checkout->kind === 'card' ? 'Assinatura criada, aguardando sua autorização no Mercado Pago. Nenhum pagamento foi confirmado.' : 'Pix aguardando pagamento. Nenhuma baixa foi confirmada.',
            'in_process' => 'Pagamento ainda em processamento. Nenhuma baixa foi confirmada.',
            'authorized' => 'Recorrência autorizada no Mercado Pago. Nenhum pagamento confirmado foi localizado nesta consulta; o acesso não foi liberado por ela.',
            'paused' => 'A recorrência está pausada no Mercado Pago. Nenhum novo pagamento foi confirmado nesta consulta.',
            'cancelled' => 'O Mercado Pago informa que esta cobrança ou recorrência está cancelada. Isso não é confirmação de pagamento.',
            'rejected' => 'O Mercado Pago recusou esta tentativa. Nenhum pagamento foi confirmado.',
            'refunded', 'charged_back' => 'O Mercado Pago informa estorno ou contestação. O pagamento precisa de revisão administrativa.',
            default => 'Não foi possível confirmar pagamento ou liberação de acesso. Consulte o atendimento.',
        };

        return ['level' => in_array($checkout->status, ['rejected', 'refunded', 'charged_back'], true) ? 'warning' : 'info', 'message' => $message];
    }
}
