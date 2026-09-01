<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot identitas slip untuk payroll hasil IMPORT MANUAL dari Excel (bukan calculator) —
 * jabatan/status/kelas/tgl masuk diambil dari file gaji apa adanya, bukan relasi HRIS (data
 * master HRIS kadang menyimpang dari Excel final). Slip blade fallback ke sini.
 * Lihat knowledge/markdown/import-payroll-excel-manual.md §1.6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->json('import_meta')->nullable()->after('generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('import_meta');
        });
    }
};
