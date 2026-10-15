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
        Schema::table('tabs', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('closed_at');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tabs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
