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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('menu_category_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->string('photo_path')->nullable()->after('description');
            $table->boolean('show_on_menu')->default(false)->after('active');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('menu_ordering_enabled')->default(false)->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('menu_ordering_enabled');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_category_id');
            $table->dropColumn(['description', 'photo_path', 'show_on_menu']);
        });
    }
};
