<?php

namespace App\Services;

use App\Models\PlatformPaymentSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoClient
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function request(string $environment, string $method, string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();
        $token = $settings?->getAttribute($environment.'_access_token');

        if (! in_array($environment, ['test', 'production'], true) || blank($token)) {
            throw new RuntimeException('Credenciais do Mercado Pago não configuradas para este ambiente.');
        }

        $client = Http::baseUrl('https://api.mercadopago.com')->acceptJson()->asJson()
            ->withToken($token)->connectTimeout(3)->timeout(12)->withoutRedirecting();

        if ($idempotencyKey !== null) {
            $client = $client->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $client->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException) {
            throw new MercadoPagoApiException('O Mercado Pago não respondeu. O resultado da criação é desconhecido; consulte antes de tentar novamente.');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            $reason = $this->safeReason($response->json('message') ?? $response->json('error'), $token);
            $requestId = $response->header('x-request-id');
            $reference = is_string($requestId) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $requestId) ? $requestId : null;
            Log::warning('Requisição recusada ou indisponível no Mercado Pago.', [
                'environment' => $environment, 'method' => $method, 'path' => $path,
                'http_status' => $response->status(), 'reason' => $reason, 'provider_request_id' => $reference,
            ]);
            throw new MercadoPagoApiException('Mercado Pago retornou HTTP '.$response->status().': '.$reason.($reference ? ' Referência: '.$reference.'.' : ''), $response->status());
        }

        return $response->json();
    }

    private function safeReason(mixed $message, string $token): string
    {
        if (! is_string($message) || trim($message) === '') {
            return 'A API não informou um motivo legível.';
        }
        $message = str_replace($token, '[credencial omitida]', $message);
        $message = preg_replace([
            '/(?:APP_USR|TEST)-[a-zA-Z0-9_-]+/i',
            '/[a-zA-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '/https?:\/\/\S+/i', '/\b\d[\d .-]{9,}\d\b/', '/[\x00-\x1F\x7F]/',
        ], '[dado omitido]', strip_tags($message));

        return Str::limit(trim($message), 300);
    }

    public function checkoutUrl(string $url): string
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || ! in_array($parts['host'] ?? '', ['www.mercadopago.com.br', 'mercadopago.com.br'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new RuntimeException('O Mercado Pago não retornou um endereço de checkout válido.');
        }

        return $url;
    }

    public function webhookUrl(string $environment): string
    {
        return rtrim(config('app.url'), '/').route('mercado-pago.webhook', ['environment' => $environment], false);
    }
}
