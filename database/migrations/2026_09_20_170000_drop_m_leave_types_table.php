<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis Cuti tidak lagi master per perusahaan (instruksi user 2026-09-20): 4 jenis tetap untuk
 * semua perusahaan lewat App\Enum\Attendance\LeaveTypeCategoryEnum (Tahunan 12/tahun, Haid
 * 2/bulan, Melahirkan 90/pengajuan, Keguguran 45/pengajuan).
 *
 * - attendance_req_leave_details.category ditambahkan & DIISI dari m_leave_types.category
 *   (lewat leave_type_id). Baris lama tanpa leave_type_id (cuti legacy: "Cuti Besar", "Cuti
 *   Sakit", dst) dibiarkan category-nya kosong - sama seperti sebelumnya tidak ikut hitungan kuota.
 * - leave_type_id + FK-nya dihapus; nama jenis (leave_type) tetap tersimpan di tiap baris.
 * - Tabel m_leave_types dihapus, permission Shield *:LeaveTypes ikut dibersihkan.
 *
 * DATA MASTER HILANG PERMANEN (kuota per perusahaan; kuota kini tetap di enum). down() hanya
 * memulihkan struktur kosong.
 */
return new class extends Migration
{
    private const DETAILS = 'attendance_req_leave_details';

    public function up(): void
    {
        if (! Schema::hasColumn(self::DETAILS, 'category')) {
            Schema::table(self::DETAILS, fn (Blueprint $table) => $table->string('category')->nullable()->after('leave_type'));
        }

        if (Schema::hasColumn(self::DETAILS, 'leave_type_id')) {
            if (Schema::hasTable('m_leave_types')) {
                // ID master per kategori (pluck 'id' by 'category' => tiap kategori punya daftar ID-nya).
                DB::table('m_leave_types')->whereNotNull('category')->get(['id', 'category'])
                    ->groupBy('category')
                    ->each(fn ($rows, $category) => DB::table(self::DETAILS)
                        ->whereIn('leave_type_id', $rows->pluck('id')->all())
                        ->update(['category' => $category]));
            }

            $hasFk = collect(Schema::getForeignKeys(self::DETAILS))
                ->contains(fn (array $fk) => in_array('leave_type_id', $fk['columns'], true));

            if ($hasFk) {
                Schema::table(self::DETAILS, fn (Blueprint $table) => $table->dropForeign(['leave_type_id']));
            }

            Schema::dropColumns(self::DETAILS, ['leave_type_id']);
        }

        Schema::dropIfExists('m_leave_types');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('name', 'like', '%:LeaveTypes')->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('m_leave_types')) {
            Schema::create('m_leave_types', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('category')->nullable();
                $table->integer('quota_days')->default(0);
                $table->enum('reset_period', ['yearly', 'monthly', 'none'])->default('yearly');
                $table->boolean('is_paid')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn(self::DETAILS, 'leave_type_id')) {
            Schema::table(self::DETAILS, function (Blueprint $table) {
                $table->foreignUlid('leave_type_id')->nullable()->after('request_id')->constrained('m_leave_types')->nullOnDelete();
            });
        }

        if (Schema::hasColumn(self::DETAILS, 'category')) {
            Schema::dropColumns(self::DETAILS, ['category']);
        }
    }
};
