<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `category` didenormalisasi langsung ke baris pengajuan (sebelumnya cuma bisa dibaca lewat
 * JOIN ke m_permission_types.category via permission_type_id) - supaya kelompok Meninggalkan
 * Jam Kerja/Tidak Masuk Seharian/Sakit tidak perlu baris master sama sekali (cuma Izin Khusus
 * yang masih butuh master, buat nama+default_days 8 jenisnya). Lihat diskusi lanjutan
 * knowledge/markdown/rencana-izin-empat-kategori.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_req_permission_details', function (Blueprint $table) {
            $table->string('category')->nullable()->after('permission_type_id');
        });

        // Backfill baris lama dari master (yang masih ada permission_type_id) supaya payroll &
        // rekap tetap konsisten baca kolom baru ini, bukan cuma baris baru ke depannya. Loop PHP
        // (bukan UPDATE...JOIN mentah) supaya portable antar driver (MySQL produksi vs SQLite
        // testing) - datanya kecil (puluhan-ratusan baris), tidak perlu dioptimasi jadi 1 query.
        DB::table('m_permission_types')
            ->whereNotNull('category')
            ->get(['id', 'category'])
            ->each(function ($type) {
                DB::table('attendance_req_permission_details')
                    ->where('permission_type_id', $type->id)
                    ->update(['category' => $type->category]);
            });
    }

    public function down(): void
    {
        Schema::table('attendance_req_permission_details', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
