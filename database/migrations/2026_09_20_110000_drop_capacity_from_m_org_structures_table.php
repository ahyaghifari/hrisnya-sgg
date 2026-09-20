<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `m_org_structures.capacity` dihapus (instruksi user 2026-09-20). Kolom ini cuma ditampilkan
 * sebagai "Kapasitas" di 3 tabel master (Departemen/Divisi/Sub Divisi) dan ditulis oleh
 * JobPositionService dengan menimpanya memakai kuota jabatan TERAKHIR yang disetujui, jadi
 * nilainya tidak pernah mencerminkan kapasitas unit yang sebenarnya (DB dev: terisi 1 dari 86
 * baris, dan tidak cocok dengan total kuota jabatannya).
 *
 * DATA HILANG PERMANEN; down() hanya memulihkan struktur kolomnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('m_org_structures', 'capacity')) {
            Schema::dropColumns('m_org_structures', ['capacity']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('m_org_structures', 'capacity')) {
            Schema::table('m_org_structures', fn (Blueprint $table) => $table->integer('capacity')->nullable()->after('code'));
        }
    }
};
