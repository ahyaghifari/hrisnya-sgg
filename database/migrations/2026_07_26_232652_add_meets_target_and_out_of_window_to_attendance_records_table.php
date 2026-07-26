<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FlexibleScheduleCalculator/FixedScheduleCalculator sudah menghitung meets_target &
     * out_of_window tiap kali sync jalan, tapi keduanya cuma dipakai buat print console di
     * command diagnostic manual (attendance:process-access-logs) - jalur cron otomatis
     * (attendance:sync-access-logs) menghitungnya lalu membuang, tidak pernah tersimpan.
     * meets_target nullable karena cuma relevan kalau sudah check-out (null = belum check-out).
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->boolean('meets_target')->nullable()->after('work_day_weight');
            $table->boolean('out_of_window')->default(false)->after('meets_target');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['meets_target', 'out_of_window']);
        });
    }
};
