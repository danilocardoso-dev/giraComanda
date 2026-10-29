<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptionIds = DB::table('subscriptions')
            ->where('monthly_amount_cents', 19700)
            ->pluck('id');

        if ($subscriptionIds->isEmpty()) {
            return;
        }

        DB::table('subscriptions')
            ->whereIn('id', $subscriptionIds)
            ->update([
                'monthly_amount_cents' => 29700,
                'updated_at' => now(),
            ]);

        DB::table('subscriptions')
            ->whereIn('id', $subscriptionIds)
            ->where('yearly_amount_cents', 197000)
            ->update([
                'yearly_amount_cents' => 297000,
                'updated_at' => now(),
            ]);

        DB::table('subscription_invoices')
            ->whereIn('subscription_id', $subscriptionIds)
            ->whereNull('paid_at')
            ->where('amount_cents', 19700)
            ->update([
                'amount_cents' => 29700,
                'updated_at' => now(),
            ]);

        DB::table('subscription_invoices')
            ->whereIn('subscription_id', $subscriptionIds)
            ->whereNull('paid_at')
            ->where('amount_cents', 197000)
            ->update([
                'amount_cents' => 297000,
                'updated_at' => now(),
            ]);
    }

    public function down(): void {}
};
