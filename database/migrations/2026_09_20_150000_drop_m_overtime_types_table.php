<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis lembur tidak lagi master per perusahaan (instruksi user 2026-09-20): tetap 2 jenis untuk
 * semua perusahaan (App\Enum\Attendance\OvertimeTypeEnum: Lembur Biasa, Lembur Khusus/Proyek).
 *
 * - "Lembur Malam" (& "Lembur Hari Libur" kalau ada) di detail pengajuan dilebur ke "Lembur Biasa".
 * - attendance_req_overtime_details.overtime_type_id + FK-nya dihapus; nama jenis (overtime_type)
 *   sudah tersimpan di tiap baris dan tetap dipakai rekap/notifikasi.
 * - Tabel m_overtime_types dihapus, permission Shield *:OvertimeTypes ikut dibersihkan.
 *
 * DATA MASTER HILANG PERMANEN (3 baris di dev); down() hanya memulihkan struktur kosong.
 */
return new class extends Migration
{
    private const DETAILS = 'attendance_req_overtime_details';

    public function up(): void
    {
        if (Schema::hasTable(self::DETAILS)) {
            DB::table(self::DETAILS)
                ->whereIn('overtime_type', ['Lembur Malam', 'Lembur Hari Libur'])
                ->update(['overtime_type' => 'Lembur Biasa']);

            if (Schema::hasColumn(self::DETAILS, 'overtime_type_id')) {
                $hasFk = collect(Schema::getForeignKeys(self::DETAILS))
                    ->contains(fn (array $fk) => in_array('overtime_type_id', $fk['columns'], true));

                if ($hasFk) {
                    Schema::table(self::DETAILS, fn (Blueprint $table) => $table->dropForeign(['overtime_type_id']));
                }

                Schema::dropColumns(self::DETAILS, ['overtime_type_id']);
            }
        }

        Schema::dropIfExists('m_overtime_types');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('name', 'like', '%:OvertimeTypes')->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('m_overtime_types')) {
            Schema::create('m_overtime_types', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->cascadeOnDelete();
                $table->string('name');
                $table->enum('day_type', ['weekday', 'weekend'])->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable(self::DETAILS) && ! Schema::hasColumn(self::DETAILS, 'overtime_type_id')) {
            Schema::table(self::DETAILS, function (Blueprint $table) {
                $table->foreignUlid('overtime_type_id')->nullable()->after('request_id')->constrained('m_overtime_types')->nullOnDelete();
            });
        }
    }
};
