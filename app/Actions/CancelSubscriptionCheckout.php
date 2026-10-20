<?php

namespace App\Actions;

use App\Models\SubscriptionCheckout;
use App\Services\MercadoPagoClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CancelSubscriptionCheckout
{
    public function __construct(private MercadoPagoClient $client, private SynchronizeMercadoPago $synchronize) {}

    /** @return array{level: string, message: string, remote_cancelled: bool} */
    public function handle(SubscriptionCheckout $checkout): array
    {
        return Cache::lock('subscription-checkout-'.$checkout->subscription_id, 90)->block(3, function () use ($checkout): array {
            $checkout->refresh();
            if ($checkout->status === 'rejected' && $checkout->remote_id === null) {
                return ['level' => 'info', 'message' => 'A criação foi recusada. Não há recorrência confirmada para cancelar. Corrija o motivo da recusa e inicie uma nova tentativa.', 'remote_cancelled' => false];
            }
            if ($checkout->status === 'cancelled') {
                return ['level' => 'info', 'message' => $checkout->remote_id ? 'O cancelamento desta recorrência já está registrado.' : 'Esta tentativa já foi encerrada localmente, sem confirmação de cancelamento no Mercado Pago.', 'remote_cancelled' => false];
            }
            if ($checkout->remote_id === null) {
                $this->synchronize->recover($checkout);
            } elseif ($checkout->kind === 'pix') {
                $this->synchronize->checkout($checkout);
            }
            $checkout->refresh();
            if ($checkout->remote_id === null) {
                if ($checkout->created_at->gt(now()->subMinutes(10))) {
                    throw new RuntimeException('A criação não foi confirmada. Aguarde 10 minutos desde a tentativa e consulte antes de encerrar o registro local. Nenhum cancelamento foi confirmado.');
                }
                $message = 'Tentativa encerrada apenas no GiraComanda: nenhum registro foi localizado para ela no Mercado Pago. Não houve confirmação de cancelamento de cobrança externa.';
                $checkout->update(['status' => 'cancelled', 'cancelled_at' => now(), 'failure_message' => $message]);

                return ['level' => 'info', 'message' => $message, 'remote_cancelled' => false];
            }
            if ($checkout->kind === 'pix' && ! in_array($checkout->status, ['pending', 'in_process'], true)) {
                return ['level' => 'warning', 'message' => 'O Pix não foi cancelado. Situação informada pelo Mercado Pago: '.$checkout->status.'. Confira o pagamento antes de iniciar outra cobrança.', 'remote_cancelled' => false];
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

            return ['level' => 'success', 'message' => 'Cancelamento confirmado pelo Mercado Pago. O período já pago foi preservado.', 'remote_cancelled' => true];
        });
    }
}
