<?php

namespace App\Http\Controllers;

use App\Actions\SynchronizeAbacatePay;
use App\Models\PlatformPaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AbacatePayWebhookController extends Controller
{
    public function __invoke(Request $request, SynchronizeAbacatePay $synchronize, string $environment): JsonResponse
    {
        $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();
        $secret = $settings?->getAttribute($environment.'_webhook_secret');
        if (blank($secret)) {
            return response()->json(['message' => 'Webhook ainda não configurado.'], 503);
        }

        // The AbacatePay "webhookSecret" query parameter proves the origin of the call.
        $received = $request->query('webhookSecret');
        if (! is_string($received) || ! hash_equals($secret, $received)) {
            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }

        $payload = $request->json()->all();
        if (! is_string($payload['event'] ?? null)) {
            return response()->json(['message' => 'Evento inválido.'], 400);
        }

        try {
            $synchronize->event($environment, $payload);
        } catch (Throwable $exception) {
            Log::warning('Falha ao processar notificação AbacatePay.', ['environment' => $environment, 'event' => $payload['event'], 'delivery_id' => $payload['id'] ?? null, 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Notificação não processada. Tente novamente.'], 503);
        }

        return response()->json(['received' => true]);
    }
}
