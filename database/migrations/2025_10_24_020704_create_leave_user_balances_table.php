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
        Schema::create('leave_user_balances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('leave_type_id')->constrained('m_leave_types')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('quota')->default(0);
            $table->integer('used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'leave_type_id', 'period_start']);
        });

        Schema::table('m_leave_types', function (Blueprint $table) {
            $table->enum('reset_period', ['yearly', 'monthly', 'none'])->default('yearly')->after('quota_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_user_balances');
    }
};
