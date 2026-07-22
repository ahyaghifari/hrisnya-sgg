<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Angka hari-kerja efektif (0 / 0.5 / counted_work_days, biasanya 1) buat rekap payroll -
     * dihitung sekali oleh calculator saat itu (bandingkan working_minutes ke
     * half_day_threshold_minutes/absent_threshold_minutes config jadwal hari itu), disimpan
     * permanen. Null = belum dihitung (mis. checkout kosong, atau baris dari import Excel lama
     * yang tidak lewat pipeline ini). Lihat komentar kolom half_day_threshold_minutes/
     * absent_threshold_minutes/counted_work_days di schedule_fixed_days/schedule_flexible_days
     * untuk arti aslinya.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->decimal('work_day_weight', 3, 1)->nullable()->after('overtime_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('work_day_weight');
        });
    }
};
