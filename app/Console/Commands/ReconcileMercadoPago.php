<?php

namespace App\Console\Commands;

use App\Actions\SynchronizeMercadoPago;
use App\Models\SubscriptionCheckout;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('subscriptions:reconcile-mercado-pago {--limit=50 : Máximo de checkouts por execução}')]
#[Description('Consulta pagamentos e recorrências para recuperar notificações perdidas')]
class ReconcileMercadoPago extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SynchronizeMercadoPago $synchronize): int
    {
        $checkouts = SubscriptionCheckout::query()->where(fn ($query) => $query->whereNotNull('remote_id')->orWhere('status', 'creating'))->where(function ($query): void {
            $query->whereNotIn('status', ['cancelled', 'rejected', 'approved', 'refunded', 'charged_back'])
                ->orWhere('created_at', '>=', now()->subDays(30));
        })->orderBy('last_attempted_at')->orderBy('id')->limit(max(1, min(100, (int) $this->option('limit'))))->get();
        $failures = 0;
        $completed = 0;
        $deadline = microtime(true) + 180;
        foreach ($checkouts as $checkout) {
            try {
                $synchronize->checkout($checkout);
                $completed++;
            } catch (Throwable) {
                $failures++;
                Log::warning('Falha na conciliação Mercado Pago.', ['checkout_id' => $checkout->id]);
            }
            if (microtime(true) >= $deadline) {
                break;
            }
        }
        $this->info('Consultas concluídas: '.$completed.'. Falhas: '.$failures.'.');

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
