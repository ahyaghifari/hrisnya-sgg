<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan manual karyawan di tabel Detail Penjadwalan (2026-09-21). Null = belum diurut manual,
 * jatuh ke urutan default (tanggal bergabung terlama dulu) di belakang yang sudah punya urutan.
 *
 * - schedule_shift_team_members.sort_order: urutan di dalam tim, PER BULAN (baris ini memang per periode).
 * - schedule_assignments.sort_order: urutan untuk karyawan "Tanpa Tim" (atau jadwal yang tidak memakai
 *   Tim) - tingkat jadwal, tidak per bulan (karyawan tanpa tim tidak punya baris per periode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_shift_team_members', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable()->after('period');
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable()->after('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_shift_team_members', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
