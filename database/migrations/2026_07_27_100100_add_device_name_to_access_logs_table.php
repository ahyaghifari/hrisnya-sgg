<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama device dari HikCentral (bisa berubah kalau di-rename di sana) - dicocokkan
 * AccessLogScheduleReader terhadap attendance_devices milik company user untuk menandai
 * attendance_records.has_unrecognized_device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->string('device_name')->nullable()->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn('device_name');
        });
    }
};
