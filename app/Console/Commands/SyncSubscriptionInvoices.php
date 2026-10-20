<?php

namespace App\Console\Commands;

use App\Actions\SynchronizeSubscriptionInvoices;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:sync-invoices')]
#[Description('Gera a primeira cobrança após o teste e sincroniza mensalidades de assinaturas ativas')]
class SyncSubscriptionInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices): int
    {
        $count = $synchronizeSubscriptionInvoices->handleAll();

        $this->info("{$count} mensalidades sincronizadas.");

        return self::SUCCESS;
    }
}
