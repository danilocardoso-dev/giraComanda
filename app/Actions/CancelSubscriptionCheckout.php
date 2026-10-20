<?php

namespace App\Actions;

use App\Models\SubscriptionCheckout;
use App\Services\MercadoPagoClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CancelSubscriptionCheckout
{
    public function __construct(private MercadoPagoClient $client, private SynchronizeMercadoPago $synchronize) {}

    public function handle(SubscriptionCheckout $checkout): void
    {
        Cache::lock('subscription-checkout-'.$checkout->subscription_id, 90)->block(3, function () use ($checkout): void {
            $checkout->refresh();
            if ($checkout->status === 'cancelled') {
                return;
            }
            if ($checkout->remote_id === null || $checkout->kind === 'pix') {
                $this->synchronize->checkout($checkout);
            }
            $checkout->refresh();
            if ($checkout->remote_id === null) {
                if ($checkout->created_at->gt(now()->subMinutes(10))) {
                    throw new RuntimeException('A criação está em verificação. Aguarde 10 minutos e consulte antes de cancelar.');
                }
                $checkout->update(['status' => 'cancelled', 'cancelled_at' => now()]);

                return;
            }
            if ($checkout->kind === 'pix' && ! in_array($checkout->status, ['pending', 'in_process'], true)) {
                return;
            }
            $path = $checkout->kind === 'pix' ? '/v1/payments/' : '/preapproval/';
            if ($checkout->kind === 'card') {
                $data = $this->client->request($checkout->environment, 'GET', $path.rawurlencode($checkout->remote_id));
                if (($data['external_reference'] ?? '') !== $checkout->uuid || (string) ($data['id'] ?? '') !== $checkout->remote_id) {
                    throw new RuntimeException('Não foi possível confirmar a identificação da recorrência.');
                }
            }
            $this->client->request($checkout->environment, 'PUT', $path.rawurlencode($checkout->remote_id), ['status' => 'cancelled']);
            if ($checkout->kind === 'card') {
                $data = $this->client->request($checkout->environment, 'GET', $path.rawurlencode($checkout->remote_id));
                if (($data['external_reference'] ?? '') !== $checkout->uuid || (string) ($data['id'] ?? '') !== $checkout->remote_id || ($data['status'] ?? '') !== 'cancelled') {
                    throw new RuntimeException('O cancelamento ainda não foi confirmado. Consulte novamente.');
                }
                $checkout->update(['status' => 'cancelled']);
            } else {
                $this->synchronize->checkout($checkout);
            }
            if ($checkout->refresh()->status !== 'cancelled') {
                throw new RuntimeException('O cancelamento ainda não foi confirmado. Consulte novamente.');
            }
            $checkout->update(['cancelled_at' => now()]);
        });
    }
}
