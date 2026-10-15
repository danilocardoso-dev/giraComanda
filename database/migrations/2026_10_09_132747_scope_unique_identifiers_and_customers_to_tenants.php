<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->restrictOnDelete();
        });

        $initialCompanyId = DB::table('companies')->where('slug', 'empresa-inicial')->value('id');
        $initialBranchId = DB::table('branches')
            ->where('company_id', $initialCompanyId)
            ->where('slug', 'matriz')
            ->value('id');

        DB::table('customers')->whereNull('branch_id')->update(['branch_id' => $initialBranchId]);

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->unique(['company_id', 'cpf']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'code']);
            $table->unique('code');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->unique('name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'cpf']);
            $table->unique('cpf');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
