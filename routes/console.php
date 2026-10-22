<?php

use App\Models\Subscription;
use App\Models\SubscriptionCheckout;
use App\Models\SubscriptionGatewayPayment;
use App\Models\SubscriptionInvoice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database')
    ->dailyAt('02:00')
    ->withoutOverlapping(10);

Schedule::command('subscriptions:sync-invoices')
    ->dailyAt('00:10')
    ->withoutOverlapping(10);

Schedule::command('subscriptions:reconcile-mercado-pago')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Artisan::command('subscriptions:reset-checkouts {--price= : Valor da mensalidade em centavos (ex: 100 para R$ 1,00)}', function () {
    SubscriptionGatewayPayment::query()->delete();
    SubscriptionCheckout::query()->delete();

    $price = $this->option('price') !== null ? (int) $this->option('price') : config('giracomanda.subscription.monthly_amount_cents');
    if ($price > 0) {
        Subscription::query()->update(['monthly_amount_cents' => $price]);
        SubscriptionInvoice::query()->whereNull('paid_at')->update(['amount_cents' => $price]);
    }

    SubscriptionInvoice::query()->whereNotNull('paid_at')->update(['paid_at' => null, 'payment_method' => null]);
    $this->info('Cobranças limpas e mensalidade ajustada para R$ '.number_format($price / 100, 2, ',', '.').'.');
})->purpose('Limpar tentativas e checkouts para novo teste de pagamento');
