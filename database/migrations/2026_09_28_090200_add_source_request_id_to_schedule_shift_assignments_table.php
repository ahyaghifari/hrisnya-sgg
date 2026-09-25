<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautan ke attendance_requests yang mengunci sel roster ini jadi Libur (schedule_status
     * cuti/izin) — dipakai AttendaceReqService::revertApprovedAbsenceSync() buka kunci lagi
     * begitu request itu dibatalkan.
     */
    public function up(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->foreignUlid('source_request_id')->nullable()->after('schedule_status')
                ->constrained('attendance_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->dropForeign(['source_request_id']);
            $table->dropColumn('source_request_id');
        });
    }
};
