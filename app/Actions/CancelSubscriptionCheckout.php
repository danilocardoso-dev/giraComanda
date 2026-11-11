<?php

namespace App\Actions;

use App\Models\SubscriptionCheckout;
use App\Services\AbacatePayClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CancelSubscriptionCheckout
{
    public function __construct(private AbacatePayClient $client, private SynchronizeAbacatePay $synchronize) {}

    /** @return array{level: string, message: string, remote_cancelled: bool} */
    public function handle(SubscriptionCheckout $checkout): array
    {
        return Cache::lock('subscription-checkout-'.$checkout->subscription_id, 90)->block(3, function () use ($checkout): array {
            $checkout->refresh();
            if ($checkout->status === 'rejected' && $checkout->remote_id === null) {
                return ['level' => 'info', 'message' => 'A criação foi recusada. Não há recorrência confirmada para cancelar. Corrija o motivo da recusa e inicie uma nova tentativa.', 'remote_cancelled' => false];
            }
            if ($checkout->status === 'cancelled') {
                return ['level' => 'info', 'message' => $checkout->remote_id ? 'O cancelamento desta cobrança ou recorrência já está registrado.' : 'Esta tentativa já foi encerrada localmente, sem confirmação de cancelamento no AbacatePay.', 'remote_cancelled' => false];
            }
            if ($checkout->remote_id === null) {
                $this->synchronize->recover($checkout);
            } else {
                $this->synchronize->checkout($checkout);
            }
            $checkout->refresh();
            if ($checkout->remote_id === null) {
                if ($checkout->created_at->gt(now()->subMinutes(10))) {
                    throw new RuntimeException('A criação não foi confirmada. Aguarde 10 minutos desde a tentativa e consulte antes de encerrar o registro local. Nenhum cancelamento foi confirmado.');
                }
                $message = 'Tentativa encerrada apenas no GiraComanda: nenhum registro foi localizado para ela no AbacatePay. Não houve confirmação de cancelamento de cobrança externa.';
                $checkout->update(['status' => 'cancelled', 'cancelled_at' => now(), 'failure_message' => $message]);

                return ['level' => 'info', 'message' => $message, 'remote_cancelled' => false];
            }
            if ($checkout->status === 'cancelled') {
                return ['level' => 'info', 'message' => 'O AbacatePay já informa esta cobrança ou recorrência como cancelada ou expirada.', 'remote_cancelled' => false];
            }

            if ($checkout->kind === 'pix') {
                if ($checkout->status !== 'pending') {
                    return ['level' => 'warning', 'message' => 'O Pix não foi encerrado. Situação informada pelo AbacatePay: '.$checkout->status.'. Confira o pagamento antes de iniciar outra cobrança.', 'remote_cancelled' => false];
                }
                // AbacatePay has no cancel endpoint for pending Pix charges: they simply expire.
                $checkout->update(['status' => 'cancelled', 'cancelled_at' => now()]);

                return ['level' => 'info', 'message' => 'Pix encerrado no GiraComanda. O AbacatePay não permite cancelar o QR Code; ele expira sozinho e não deve ser pago.', 'remote_cancelled' => false];
            }

            if ($checkout->provider_subscription_id === null) {
                $this->client->request($checkout->environment, 'POST', '/subscriptions/checkouts/delete', ['id' => $checkout->remote_id]);
            } else {
                $data = $this->client->request($checkout->environment, 'POST', '/subscriptions/cancel', ['id' => $checkout->provider_subscription_id]);
                if (($data['status'] ?? '') !== 'CANCELLED') {
                    throw new RuntimeException('O cancelamento ainda não foi confirmado. Consulte novamente.');
                }
            }
            $checkout->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return ['level' => 'success', 'message' => 'Cancelamento confirmado pelo AbacatePay. O período já pago foi preservado.', 'remote_cancelled' => true];
        });
    }
}
