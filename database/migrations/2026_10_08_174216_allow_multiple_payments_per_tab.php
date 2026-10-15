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
        Schema::table('payments', function (Blueprint $table) {
            $table->index('tab_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['tab_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('tab_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tab_id']);
        });
    }
};
