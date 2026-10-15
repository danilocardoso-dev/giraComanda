<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_phone', 11)->nullable()->after('billing_day');
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->boolean('generated_automatically')->default(false)->after('notes');
        });

        DB::table('subscriptions')
            ->orderBy('id')
            ->each(function (object $subscription): void {
                $phone = DB::table('establishment_settings')
                    ->where('company_id', $subscription->company_id)
                    ->value('phone');
                $digits = preg_replace('/\D/', '', (string) $phone);

                if (in_array(strlen($digits), [10, 11], true)) {
                    DB::table('subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['billing_phone' => $digits]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn('generated_automatically');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_phone');
        });
    }
};
