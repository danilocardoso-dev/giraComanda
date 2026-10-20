<?php

namespace App\Models;

use Database\Factories\PlatformPaymentSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'environment', 'enabled', 'test_public_key', 'test_access_token', 'test_webhook_secret', 'test_plan_id', 'production_public_key', 'production_access_token', 'production_webhook_secret', 'production_plan_id'])]
#[Hidden(['test_public_key', 'test_access_token', 'test_webhook_secret', 'production_public_key', 'production_access_token', 'production_webhook_secret'])]
class PlatformPaymentSetting extends Model
{
    /** @use HasFactory<PlatformPaymentSettingFactory> */
    use HasFactory;

    public const PROVIDER = 'mercado_pago';

    public const ENVIRONMENTS = ['test' => 'Testes', 'production' => 'Produção'];

    public const CREDENTIAL_LABELS = [
        'public_key' => 'Public Key',
        'access_token' => 'Access Token',
        'webhook_secret' => 'Segredo do webhook',
    ];

    public const CREDENTIAL_FIELDS = [
        'test_public_key',
        'test_access_token',
        'test_webhook_secret',
        'production_public_key',
        'production_access_token',
        'production_webhook_secret',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', ...array_fill_keys(self::CREDENTIAL_FIELDS, 'encrypted')];
    }

    public function ready(): bool
    {
        return $this->enabled && filled($this->getAttribute($this->environment.'_access_token'))
            && filled($this->getAttribute($this->environment.'_webhook_secret'));
    }
}
