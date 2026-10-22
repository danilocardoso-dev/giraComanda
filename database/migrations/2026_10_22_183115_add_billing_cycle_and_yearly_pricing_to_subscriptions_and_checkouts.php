<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->after('status');
            $table->unsignedBigInteger('yearly_amount_cents')->nullable()->after('monthly_amount_cents');
        });

        Schema::table('subscription_checkouts', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->after('kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_checkouts', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'yearly_amount_cents']);
        });
    }
};
