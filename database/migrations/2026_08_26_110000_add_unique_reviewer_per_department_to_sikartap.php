<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1 reviewer per Departemen (2026-08-26, instruksi user - disederhanakan dari sebelumnya boleh
 * banyak reviewer per node). Dedupe dulu (simpan baris terbaru per company_id+org_structure_id,
 * hapus sisanya) sebelum unique index ditambahkan, supaya tidak gagal kalau kebetulan sudah ada
 * data multi-reviewer dari sesi sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keepIds = DB::table('sikartap_ide_inovasi_reviewers')
            ->selectRaw('MAX(id) as id')
            ->groupBy('company_id', 'org_structure_id')
            ->pluck('id');

        DB::table('sikartap_ide_inovasi_reviewers')
            ->whereNotIn('id', $keepIds)
            ->delete();

        Schema::table('sikartap_ide_inovasi_reviewers', function (Blueprint $table) {
            $table->unique(['company_id', 'org_structure_id'], 'sikartap_reviewers_company_org_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sikartap_ide_inovasi_reviewers', function (Blueprint $table) {
            $table->dropUnique('sikartap_reviewers_company_org_unique');
        });
    }
};
