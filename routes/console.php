<?php

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
