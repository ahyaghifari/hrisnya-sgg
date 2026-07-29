<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian Omset Rumah Sakit per kategori per periode gaji — dijumlah jadi Total Omset
     * dipakai JasaPelayananCalculator (menggantikan service_fee_pools.total_amount yang cuma
     * 1 angka polos). Company TIDAK disimpan di sini sengaja — sudah didapat lewat
     * payroll_period_id -> payroll_periods.company_id, tidak perlu kolom redundan.
     */
    public function up(): void
    {
        Schema::create('omset_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUlid('omset_category_id')->constrained('omset_categories')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'omset_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omset_details');
    }
};
