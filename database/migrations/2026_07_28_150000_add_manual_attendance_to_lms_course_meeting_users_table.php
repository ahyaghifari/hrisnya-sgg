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
        Schema::table('lms_course_meeting_users', function (Blueprint $table) {
            // Absensi manual per pertemuan (dicentang admin dari portal) — beda dari is_attended/
            // is_present yang dipakai clock-in mobile otomatis (MeetingAttendanceService::clockIn,
            // menandakan hadir/tepat-waktu, bukan pasangan masuk-pulang). Kolom ini sengaja
            // terpisah supaya tidak bentrok makna dengan clock-in yang sudah ada.
            $table->boolean('absen_masuk')->default(false)->after('is_present');
            $table->boolean('absen_pulang')->default(false)->after('absen_masuk');
            $table->string('keterangan_kehadiran')->nullable()->after('absen_pulang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_course_meeting_users', function (Blueprint $table) {
            $table->dropColumn(['absen_masuk', 'absen_pulang', 'keterangan_kehadiran']);
        });
    }
};
