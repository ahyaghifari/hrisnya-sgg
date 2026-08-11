<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roster pendapatan/potongan manual khusus untuk User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS
     * (Presiden Direktur & Sekretaris Direktur — 2 akun yang sengaja dikecualikan dari alur
     * karyawan biasa lewat global scope 'non_karyawan', lihat User::NON_KARYAWAN_IDS). Beda
     * dari Incentive (cuma earning) - di sini `type` bisa earning ATAU deduction, `label` bebas
     * (bukan terikat PayrollComponentEnum yang tertutup 18 komponen) - dipakai contoh awal buat
     * BPJS Kesehatan/Ketenagakerjaan nominal tetap (bukan basis x persen dari Pangkat seperti
     * karyawan biasa), tapi sengaja generik supaya bisa dipakai baris lain juga ke depan.
     * Dibaca ExecutiveAllowanceCalculator (always-on, sama pola FeeClaimCalculator) tiap
     * Generate/Regenerate - bukan terikat 1 periode spesifik, sama seperti roster Incentive.
     */
    public function up(): void
    {
        Schema::create('executive_allowances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['earning', 'deduction']);
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_allowances');
    }
};
