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
        Schema::table('receivables', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_cents')->default(0)->after('amount_cents');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('tab_id')->constrained()->restrictOnDelete();
            $table->foreignId('tab_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->foreignId('tab_id')->nullable(false)->change();
        });

        Schema::table('receivables', function (Blueprint $table) {
            $table->dropColumn('paid_cents');
        });
    }
};
