<?php

namespace App\Services;

use App\Models\PlatformPaymentSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AbacatePayClient
{
    public const BASE_URL = 'https://api.abacatepay.com/v2';

    /**
     * Calls the AbacatePay API and returns the unwrapped `data` payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<int|string, mixed>
     */
    public function request(string $environment, string $method, string $path, array $data = []): array
    {
        $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();
        $token = $settings?->getAttribute($environment.'_access_token');

        if (! in_array($environment, ['test', 'production'], true) || blank($token)) {
            throw new RuntimeException('Credenciais do AbacatePay não configuradas para este ambiente.');
        }

        $client = Http::baseUrl(self::BASE_URL)->acceptJson()->asJson()
            ->withToken($token)->connectTimeout(3)->timeout(12)->withoutRedirecting();

        try {
            $response = $client->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException) {
            throw new AbacatePayApiException('O AbacatePay não respondeu. O resultado da operação é desconhecido; consulte antes de tentar novamente.');
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['success'] ?? true) === false || ! array_key_exists('data', $body)) {
            $reason = $this->safeReason($body['error'] ?? ($body['message'] ?? null), $token);
            Log::warning('Requisição recusada ou indisponível no AbacatePay.', [
                'environment' => $environment, 'method' => $method, 'path' => $path,
                'http_status' => $response->status(), 'reason' => $reason,
            ]);
            throw new AbacatePayApiException('AbacatePay retornou HTTP '.$response->status().': '.$reason, $response->status());
        }

        return is_array($body['data']) ? $body['data'] : [];
    }

    private function safeReason(mixed $message, string $token): string
    {
        if (! is_string($message) || trim($message) === '') {
            return 'A API não informou um motivo legível.';
        }
        $message = str_replace($token, '[credencial omitida]', $message);
        $message = preg_replace([
            '/abc_(?:dev|prod)_[a-zA-Z0-9_-]+/i',
            '/[a-zA-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '/https?:\/\/\S+/i', '/\b\d[\d .-]{9,}\d\b/', '/[\x00-\x1F\x7F]/',
        ], '[dado omitido]', strip_tags($message));

        return Str::limit(trim($message), 300);
    }

    public function checkoutUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (($parts['scheme'] ?? '') !== 'https' || ! ($host === 'abacatepay.com' || str_ends_with($host, '.abacatepay.com'))
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new RuntimeException('O AbacatePay não retornou um endereço de checkout válido.');
        }

        return $url;
    }

    public function webhookUrl(string $environment): string
    {
        return rtrim(config('app.url'), '/').route('abacatepay.webhook', ['environment' => $environment], false);
    }
}
