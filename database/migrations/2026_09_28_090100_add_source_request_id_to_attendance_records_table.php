<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautan ke attendance_requests yang membuat baris ini lewat sync approval (source =
     * 'leave_approved') — dipakai AttendaceReqService::revertApprovedAbsenceSync() supaya hapus
     * baris yang presisi punya request itu, bukan tebak dari kolom string.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignUlid('source_request_id')->nullable()->after('source')
                ->constrained('attendance_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['source_request_id']);
            $table->dropColumn('source_request_id');
        });
    }
};
