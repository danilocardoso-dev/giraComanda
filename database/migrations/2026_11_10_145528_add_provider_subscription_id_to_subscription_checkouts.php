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
        Schema::table('subscription_checkouts', function (Blueprint $table) {
            $table->string('provider_subscription_id', 100)->nullable()->index()->after('remote_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_checkouts', function (Blueprint $table) {
            $table->dropColumn('provider_subscription_id');
        });
    }
};
