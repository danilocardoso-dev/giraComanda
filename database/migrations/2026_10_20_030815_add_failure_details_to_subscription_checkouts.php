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
            $table->text('failure_message')->nullable();
            $table->unsignedSmallInteger('failure_http_status')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_checkouts', function (Blueprint $table) {
            $table->dropColumn(['failure_message', 'failure_http_status', 'failed_at', 'last_attempted_at']);
        });
    }
};
