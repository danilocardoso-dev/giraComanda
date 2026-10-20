<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptionIds = DB::table('subscriptions')
            ->where('monthly_amount_cents', 39900)
            ->pluck('id');

        if ($subscriptionIds->isEmpty()) {
            return;
        }

        DB::table('subscriptions')
            ->whereIn('id', $subscriptionIds)
            ->update([
                'monthly_amount_cents' => 19700,
                'updated_at' => now(),
            ]);

        DB::table('subscription_invoices')
            ->whereIn('subscription_id', $subscriptionIds)
            ->where('amount_cents', 39900)
            ->whereNull('paid_at')
            ->update([
                'amount_cents' => 19700,
                'updated_at' => now(),
            ]);
    }

    public function down(): void {}
};
