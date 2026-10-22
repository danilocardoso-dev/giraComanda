<?php

namespace App\Models;

use Database\Factories\SubscriptionCheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subscription_id', 'subscription_invoice_id', 'uuid', 'environment', 'kind', 'status', 'remote_id', 'amount_cents', 'payer_email', 'checkout_url', 'pix_code', 'expires_at', 'last_synced_at', 'cancelled_at', 'reconciliation_offset', 'failure_message', 'failure_http_status', 'failed_at', 'last_attempted_at'])]
#[Hidden(['payer_email'])]
class SubscriptionCheckout extends Model
{
    /** @use HasFactory<SubscriptionCheckoutFactory> */
    use HasFactory;

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionGatewayPayment::class);
    }

    public function isRecurring(): bool
    {
        return $this->kind === 'card' && ! in_array($this->status, ['cancelled', 'rejected'], true);
    }

    public function statusLabel(): string
    {
        if ($this->status === 'authorized') {
            $lastPayment = $this->payments()->latest('id')->first();
            if ($lastPayment?->status === 'approved' && ($this->subscription?->allowsAccess() || $this->invoice?->paid_at !== null)) {
                return 'Recorrência ativa (em dia)';
            }
        }

        return match ($this->status) {
            'creating' => 'Criação não confirmada',
            'pending' => $this->kind === 'card' ? 'Aguardando autorização' : 'Aguardando pagamento',
            'in_process' => 'Pagamento em processamento',
            'authorized' => 'Recorrência autorizada; pagamento depende de confirmação',
            'paused' => 'Recorrência pausada',
            'approved' => 'Pagamento aprovado no provedor',
            'rejected' => $this->remote_id === null ? 'Criação recusada' : 'Pagamento recusado',
            'cancelled' => $this->remote_id === null ? 'Tentativa encerrada localmente' : 'Cancelamento confirmado no provedor',
            'refunded' => 'Pagamento estornado',
            'charged_back' => 'Pagamento contestado',
            default => 'Situação não reconhecida; requer conferência',
        };
    }

    protected function casts(): array
    {
        return ['payer_email' => 'encrypted', 'amount_cents' => 'integer', 'expires_at' => 'datetime', 'last_synced_at' => 'datetime', 'cancelled_at' => 'datetime', 'failure_http_status' => 'integer', 'failed_at' => 'datetime', 'last_attempted_at' => 'datetime'];
    }
}
