<?php

namespace App\Models;

use Database\Factories\SubscriptionCheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subscription_id', 'subscription_invoice_id', 'uuid', 'environment', 'kind', 'status', 'remote_id', 'amount_cents', 'payer_email', 'checkout_url', 'pix_code', 'expires_at', 'last_synced_at', 'cancelled_at', 'reconciliation_offset'])]
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

    protected function casts(): array
    {
        return ['payer_email' => 'encrypted', 'amount_cents' => 'integer', 'expires_at' => 'datetime', 'last_synced_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
