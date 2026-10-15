<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'active']);
        });

        Schema::create('branch_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'user_id']);
            $table->index(['user_id', 'active']);
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        $companyId = DB::table('companies')->where('slug', 'empresa-inicial')->value('id');
        $branchId = DB::table('branches')->where('company_id', $companyId)->where('slug', 'matriz')->value('id');
        $now = now();

        DB::table('users')->orderBy('id')->each(function (object $user) use ($branchId, $companyId, $now): void {
            $isAdmin = $user->role === 'admin';

            DB::table('company_memberships')->insert([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'role' => $isAdmin ? 'company_admin' : 'member',
                'active' => (bool) $user->active,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('branch_memberships')->insert([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'role' => match ($user->role) {
                    'admin' => 'manager',
                    'cashier' => 'cashier',
                    default => 'attendant',
                },
                'active' => (bool) $user->active,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($isAdmin) {
                DB::table('platform_admins')->insert([
                    'user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('branch_memberships');
        Schema::dropIfExists('company_memberships');
    }
};
