<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'products', 'establishment_settings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            });
        }

        foreach (['cards', 'tabs', 'receivables', 'payments', 'cash_sessions', 'cash_movements', 'stock_movements', 'audit_logs', 'monthly_statement_deliveries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            });
        }

        $companyId = DB::table('companies')->where('slug', 'empresa-inicial')->value('id');
        $branchId = DB::table('branches')->where('company_id', $companyId)->where('slug', 'matriz')->value('id');

        foreach (['customers', 'products', 'establishment_settings'] as $tableName) {
            DB::table($tableName)->whereNull('company_id')->update(['company_id' => $companyId]);
        }

        foreach (['cards', 'tabs', 'receivables', 'payments', 'cash_sessions', 'cash_movements', 'stock_movements', 'audit_logs', 'monthly_statement_deliveries'] as $tableName) {
            DB::table($tableName)->whereNull('branch_id')->update(['branch_id' => $branchId]);
        }
    }

    public function down(): void
    {
        foreach (['cards', 'tabs', 'receivables', 'payments', 'cash_sessions', 'cash_movements', 'stock_movements', 'audit_logs', 'monthly_statement_deliveries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        foreach (['customers', 'products', 'establishment_settings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
