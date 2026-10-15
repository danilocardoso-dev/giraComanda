<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('trial');
            $table->unsignedBigInteger('monthly_amount_cents')->default(0);
            $table->unsignedTinyInteger('billing_day')->default(10);
            $table->date('started_at');
            $table->date('trial_ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'billing_day']);
        });

        $now = now();

        DB::table('companies')->orderBy('id')->each(function (object $company) use ($now): void {
            DB::table('subscriptions')->insert([
                'company_id' => $company->id,
                'status' => 'active',
                'monthly_amount_cents' => 0,
                'billing_day' => 10,
                'started_at' => $now->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
