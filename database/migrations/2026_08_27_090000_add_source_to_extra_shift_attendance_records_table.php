<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AccessLogScheduleReader sekarang bisa auto-rute hari yang punya extra shift approved ke
 * bucket 'extra_shift' (skip resolusi jadwal normal sama sekali - lihat komentar
 * AccessLogScheduleReader::groupAndBucket) - butuh kolom ini buat bedain baris yang kehitung
 * otomatis (access_logs_shift) vs input manual (manual, lewat ExtraShiftPage::recordAttendance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_shift_attendance_records', function (Blueprint $table) {
            $table->string('source')->nullable()->after('out_of_window');
        });
    }

    public function down(): void
    {
        Schema::table('extra_shift_attendance_records', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
