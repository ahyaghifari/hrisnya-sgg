<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis Izin tidak lagi master per perusahaan (instruksi user 2026-09-20): tetap & sama untuk
 * semua perusahaan lewat App\Enum\Attendance\PermissionTypeEnum (13 jenis: 3 Meninggalkan Jam
 * Kerja, Tidak Masuk, Sakit, 8 Izin Khusus).
 *
 * - attendance_req_permission_details.permission_type_id + FK-nya dihapus; nama jenis
 *   (permission_type) & category sudah tersimpan di tiap baris dan tetap dipakai rekap, payroll,
 *   dan alur persetujuan. Baris lama (termasuk yang tanpa category/ID) tidak diubah.
 * - Tabel m_permission_types dihapus, permission Shield *:PermissionTypes ikut dibersihkan.
 *
 * DATA MASTER HILANG PERMANEN (68 baris di dev, termasuk kuota hari & deskripsi yang kini ada di
 * enum); down() hanya memulihkan struktur kosong.
 */
return new class extends Migration
{
    private const DETAILS = 'attendance_req_permission_details';

    public function up(): void
    {
        if (Schema::hasTable(self::DETAILS) && Schema::hasColumn(self::DETAILS, 'permission_type_id')) {
            $hasFk = collect(Schema::getForeignKeys(self::DETAILS))
                ->contains(fn (array $fk) => in_array('permission_type_id', $fk['columns'], true));

            if ($hasFk) {
                Schema::table(self::DETAILS, fn (Blueprint $table) => $table->dropForeign(['permission_type_id']));
            }

            Schema::dropColumns(self::DETAILS, ['permission_type_id']);
        }

        Schema::dropIfExists('m_permission_types');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('name', 'like', '%:PermissionTypes')->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('m_permission_types')) {
            Schema::create('m_permission_types', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('category')->nullable();
                $table->integer('default_days')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable(self::DETAILS) && ! Schema::hasColumn(self::DETAILS, 'permission_type_id')) {
            Schema::table(self::DETAILS, function (Blueprint $table) {
                $table->foreignUlid('permission_type_id')->nullable()->after('request_id')->constrained('m_permission_types')->nullOnDelete();
            });
        }
    }
};
