<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda posisi magang - dibuat lewat halaman ringan "Posisi Magang" (grup nav Internship),
 * bukan lewat wizard JobPositionResource biasa (butuh approval/manpower planning penuh yang
 * kebanyakan buat kebutuhan magang). Posisi ini tetap baris m_job_positions biasa dengan
 * org_structure_id null (Jabatan Fungsional) supaya otomatis kepakai di ScheduleAssignmentsPage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->boolean('is_internship')->default(false)->after('org_structure_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->dropColumn('is_internship');
        });
    }
};
