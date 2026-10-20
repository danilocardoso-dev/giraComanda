<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlatformPaymentSettingRequest;
use App\Models\PlatformActivityLog;
use App\Models\PlatformPaymentSetting;
use App\Services\MercadoPagoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PlatformPaymentSettingController extends Controller
{
    public function edit(Request $request, MercadoPagoClient $client): Response
    {
        abort_unless($request->user()->active, 403);

        $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();
        $environments = [];

        foreach (PlatformPaymentSetting::ENVIRONMENTS as $environment => $label) {
            $credentials = [];

            foreach (PlatformPaymentSetting::CREDENTIAL_LABELS as $key => $credentialLabel) {
                $field = $environment.'_'.$key;
                $credentials[] = [
                    'name' => $field,
                    'label' => $credentialLabel,
                    'configured' => filled($settings?->getRawOriginal($field)),
                ];
            }

            $environments[] = [
                'key' => $environment,
                'label' => $label,
                'credentials' => $credentials,
                'plan_id' => $settings?->getAttribute($environment.'_plan_id'),
                'webhook_url' => $client->webhookUrl($environment),
            ];
        }

        return response()->view('platform.payment-settings.edit', [
            'environment' => $settings?->environment ?? 'test',
            'environments' => $environments,
            'updatedAt' => $settings?->updated_at,
            'enabled' => $settings?->enabled ?? false,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdatePlatformPaymentSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $settings = PlatformPaymentSetting::query()->firstOrCreate([
                'provider' => PlatformPaymentSetting::PROVIDER,
            ]);
            $settings->environment = $validated['environment'];
            $settings->enabled = $request->boolean('enabled');

            foreach (PlatformPaymentSetting::CREDENTIAL_FIELDS as $field) {
                if ($request->boolean('remove_'.$field)) {
                    $settings->setAttribute($field, null);
                } elseif (filled($validated[$field] ?? null)) {
                    $settings->setAttribute($field, $validated[$field]);
                }
            }

            foreach (array_keys(PlatformPaymentSetting::ENVIRONMENTS) as $environment) {
                $field = $environment.'_plan_id';

                if (array_key_exists($field, $validated)) {
                    $settings->setAttribute($field, $validated[$field]);
                }
            }

            $settings->save();

            PlatformActivityLog::record(
                $request,
                'payment_settings_updated',
                'Configuração do Mercado Pago atualizada. Ambiente selecionado: '.PlatformPaymentSetting::ENVIRONMENTS[$settings->environment].'.',
            );
        });

        return to_route('platform.payment-settings.edit')
            ->with('success', 'Configurações do Mercado Pago salvas com segurança.');
    }
}
