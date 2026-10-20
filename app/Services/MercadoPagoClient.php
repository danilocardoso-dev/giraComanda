<?php

namespace App\Services;

use App\Models\PlatformPaymentSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
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
            throw new RuntimeException('O Mercado Pago não respondeu. Tente consultar novamente em instantes.');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            // Do not persist provider bodies: they can contain payer data or credentials.
            throw new RuntimeException('Não foi possível concluir a operação no Mercado Pago (HTTP '.$response->status().').');
        }

        return $response->json();
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
