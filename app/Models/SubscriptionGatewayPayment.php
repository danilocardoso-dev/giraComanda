<?php

namespace App\Models;

use Database\Factories\SubscriptionGatewayPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_checkout_id', 'subscription_invoice_id', 'environment', 'remote_id', 'status', 'amount_cents', 'approved_at', 'applied_at', 'review_reason'])]
class SubscriptionGatewayPayment extends Model
{
    /** @use HasFactory<SubscriptionGatewayPaymentFactory> */
    use HasFactory;

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCheckout::class, 'subscription_checkout_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'approved_at' => 'datetime', 'applied_at' => 'datetime'];
    }
}
