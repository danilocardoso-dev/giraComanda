<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('customer_sharing_enabled')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'name']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'active', 'name']);
        });

        $now = now();
        $companyName = DB::table('establishment_settings')->value('name') ?: 'GiraComanda';
        $companyId = DB::table('companies')->insertGetId([
            'name' => $companyName,
            'slug' => 'empresa-inicial',
            'customer_sharing_enabled' => false,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('branches')->insert([
            'company_id' => $companyId,
            'name' => 'Matriz',
            'slug' => 'matriz',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
