<?php

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

Artisan::command('subscriptions:reset-checkouts', function () {
    SubscriptionGatewayPayment::query()->delete();
    SubscriptionCheckout::query()->delete();
    SubscriptionInvoice::query()->whereNotNull('paid_at')->update(['paid_at' => null, 'payment_method' => null]);
    $this->info('Cobranças, pagamentos e checkouts limpos com sucesso.');
})->purpose('Limpar tentativas e checkouts para novo teste de pagamento');
