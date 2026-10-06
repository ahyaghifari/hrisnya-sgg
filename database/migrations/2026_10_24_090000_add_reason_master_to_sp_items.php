<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master pelanggaran (instruksi user 2026-10-06): sp_reasons jadi master per perusahaan
 * (company_id NULL = bawaan untuk semua), dipakai Dasar Klarifikasi BA dan poin SP. `text` tetap
 * menyimpan teks final (snapshot) supaya dokumen yang sudah ditandatangani tidak berubah bila
 * master diganti nama; `reason_id` + `note` hanya untuk edit ulang. `repeat_sp_id` menandai poin
 * SP sebagai pelanggaran berulang dari SP aktif sebelumnya (rantai SP1 -> SP2 -> SP3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reasons', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->string('code')->nullable()->change();
            $table->foreignUlid('company_id')->nullable()->after('id')->constrained('m_companies')->cascadeOnDelete();
        });

        foreach (['sp_report_dasar_items' => 'sp_report_id', 'sp_items' => 'sp_id'] as $tableName => $parent) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignUlid('reason_id')->nullable()->after('text')->constrained('sp_reasons')->nullOnDelete();
                $table->text('note')->nullable()->after('reason_id');

                if ($tableName === 'sp_items') {
                    $table->foreignUlid('repeat_sp_id')->nullable()->after('note')->constrained('employee_sps')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('sp_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repeat_sp_id');
            $table->dropConstrainedForeignId('reason_id');
            $table->dropColumn('note');
        });

        Schema::table('sp_report_dasar_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reason_id');
            $table->dropColumn('note');
        });

        Schema::table('sp_reasons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
