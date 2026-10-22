<?php

namespace App\Http\Controllers;

use App\Actions\SynchronizeMercadoPago;
use App\Models\PlatformPaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, SynchronizeMercadoPago $synchronize, string $environment): JsonResponse
    {
        $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();
        $secret = $settings?->getAttribute($environment.'_webhook_secret');
        if (blank($secret)) {
            return response()->json(['message' => 'Webhook ainda não configurado.'], 503);
        }

        $id = $request->query->get('data_id')
            ?? $request->query->get('data.id')
            ?? (is_scalar($request->input('data.id')) ? (string) $request->input('data.id') : null)
            ?? (is_scalar($request->input('id')) ? (string) $request->input('id') : null);
        $requestId = $request->header('x-request-id');
        $signature = $request->header('x-signature', '');
        preg_match('/(?:^|,)\s*ts=(\d+)(?:,|$)/', $signature, $timestamp);
        preg_match('/(?:^|,)\s*v1=([a-fA-F0-9]{64})(?:,|$)/', $signature, $hash);

        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $id)
            || blank($requestId) || strlen($requestId) > 200 || ! isset($timestamp[1], $hash[1])) {
            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }
        $manifest = 'id:'.strtolower($id).';request-id:'.$requestId.';ts:'.$timestamp[1].';';
        if (! hash_equals(hash_hmac('sha256', $manifest, $secret), strtolower($hash[1]))) {
            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }
        if ($request->input('data.id') !== null && (string) $request->input('data.id') !== $id) {
            return response()->json(['message' => 'Recurso divergente.'], 400);
        }

        $type = $request->input('type') ?? $request->query->get('type') ?? $request->input('topic') ?? $request->query->get('topic');
        if (blank($type) && is_string($request->input('action'))) {
            $action = $request->input('action');
            if (str_starts_with($action, 'payment.')) {
                $type = 'payment';
            } elseif (str_starts_with($action, 'subscription_preapproval.')) {
                $type = 'subscription_preapproval';
            } elseif (str_starts_with($action, 'subscription_authorized_payment.')) {
                $type = 'subscription_authorized_payment';
            }
        }
        if ($type === 'preapproval') {
            $type = 'subscription_preapproval';
        } elseif ($type === 'authorized_payment') {
            $type = 'subscription_authorized_payment';
        }
        try {
            match ($type) {
                'payment' => $synchronize->payment($environment, $id),
                'subscription_preapproval' => $synchronize->recurrence($environment, $id),
                'subscription_authorized_payment' => $synchronize->authorizedPayment($environment, $id),
                default => null,
            };
        } catch (Throwable) {
            Log::warning('Falha ao processar notificação Mercado Pago.', ['environment' => $environment, 'resource_id' => $id]);

            return response()->json(['message' => 'Notificação não processada. Tente novamente.'], 503);
        }

        return response()->json(['received' => true]);
    }
}
