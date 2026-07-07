<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * company_id dibuat NULLABLE dan data lama TIDAK di-backfill dengan sengaja:
     * `m_doctor_services` & `m_salary_components` saat ini dipakai bersama oleh
     * lebih dari 1 company (holding & RSU Banjarbaru sama-sama mereferensikan
     * baris yang sama untuk payroll/jasa dokter). company_id = NULL berarti
     * "berlaku untuk semua company" (perilaku existing tetap sama persis),
     * kolom ini cuma dipakai kalau nanti admin sengaja bikin komponen yang
     * spesifik untuk 1 company saja.
     */
    public function up(): void
    {
        Schema::table('m_doctor_services', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')
                ->constrained('m_companies')->nullOnDelete();
        });

        Schema::table('m_salary_components', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')
                ->constrained('m_companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('m_doctor_services', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('m_salary_components', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
