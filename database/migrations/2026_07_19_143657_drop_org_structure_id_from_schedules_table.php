<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `schedules.org_structure_id` ternyata tidak pernah dipakai buat logic apa pun —
     * CreateSchedulePage sengaja tidak pernah mengisinya (penempatan jabatan sekarang lewat
     * `schedule_assignments`/ScheduleAssignmentsPage), cuma ditampilkan di kolom "Unit/Divisi"
     * SchedulesPage yang sekarang juga dihapus. Kolom nganggur, aman didrop.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['org_structure_id']);
            $table->dropColumn('org_structure_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignUlid('org_structure_id')->nullable()->after('schedule_type')->constrained('m_org_structures')->nullOnDelete();
        });
    }
};
