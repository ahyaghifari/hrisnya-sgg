<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koperasi & Bon — 100% input manual per user per periode oleh finance/koperasi, tidak
     * ada rumus otomatis sama sekali (dikonfirmasi user).
     */
    public function up(): void
    {
        Schema::create('payroll_manual_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('component'); // 'koperasi' | 'bon'
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_manual_lines');
    }
};
