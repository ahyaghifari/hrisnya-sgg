<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyederhanaan m_job_positions (instruksi user 2026-09-20):
 *
 * - `actual_manpower` di-rename jadi `manpower`. Isinya adalah KUOTA jabatan yang disetujui
 *   (bertambah tiap pengajuan kuota disetujui), BUKAN jumlah karyawan terisi — yang terisi
 *   dihitung realtime lewat JobPositions::userAssignments(). Nilainya dipertahankan utuh.
 * - `approved_manpower` dihapus. Cuma menyimpan kenaikan kuota terakhir (150 dari 154 baris = 0),
 *   jadi TIDAK boleh dipakai sebagai kuota (rename langsung ke `manpower` akan membuat kuota 0).
 * - `is_draft` dihapus; `status` (draft/requested/approved/rejected) sudah ada dan jadi satu-satunya
 *   penanda. Bagan organisasi & penugasan jadwal kini menyaring status = approved.
 * - `is_internship` (fitur Magang dicabut), `workload_score`, `file_workload_analysis` (upload),
 *   `level`, `role_id` (bigint legacy + FK ke roles, selalu NULL) dihapus.
 *
 * DATA DI KOLOM YANG DIHAPUS HILANG PERMANEN. down() hanya memulihkan struktur, bukan isi;
 * file PDF di storage `workload-analysis/` tidak ikut terhapus otomatis.
 */
return new class extends Migration
{
    private const DROPPED = [
        'approved_manpower',
        'is_draft',
        'is_internship',
        'workload_score',
        'file_workload_analysis',
        'level',
        'role_id',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('m_job_positions', 'role_id')) {
            $hasFk = collect(Schema::getForeignKeys('m_job_positions'))
                ->contains(fn (array $fk) => in_array('role_id', $fk['columns'], true));

            if ($hasFk) {
                Schema::table('m_job_positions', fn (Blueprint $table) => $table->dropForeign(['role_id']));
            }
        }

        if (Schema::hasColumn('m_job_positions', 'actual_manpower') && ! Schema::hasColumn('m_job_positions', 'manpower')) {
            Schema::table('m_job_positions', fn (Blueprint $table) => $table->renameColumn('actual_manpower', 'manpower'));
        }

        $existing = array_values(array_filter(self::DROPPED, fn (string $c) => Schema::hasColumn('m_job_positions', $c)));

        if ($existing !== []) {
            Schema::dropColumns('m_job_positions', $existing);
        }
    }

    public function down(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            if (! Schema::hasColumn('m_job_positions', 'approved_manpower')) {
                $table->integer('approved_manpower')->default(0)->after('planned_manpower');
            }
            if (! Schema::hasColumn('m_job_positions', 'is_draft')) {
                $table->boolean('is_draft')->default(true)->after('parent_id');
            }
            if (! Schema::hasColumn('m_job_positions', 'is_internship')) {
                $table->boolean('is_internship')->default(false)->after('org_structure_id');
            }
            if (! Schema::hasColumn('m_job_positions', 'workload_score')) {
                $table->integer('workload_score')->nullable();
            }
            if (! Schema::hasColumn('m_job_positions', 'file_workload_analysis')) {
                $table->text('file_workload_analysis')->nullable();
            }
            if (! Schema::hasColumn('m_job_positions', 'level')) {
                $table->unsignedInteger('level')->nullable();
            }
            if (! Schema::hasColumn('m_job_positions', 'role_id')) {
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('m_job_positions', 'manpower') && ! Schema::hasColumn('m_job_positions', 'actual_manpower')) {
            Schema::table('m_job_positions', fn (Blueprint $table) => $table->renameColumn('manpower', 'actual_manpower'));
        }

        // Pemulihan best-effort: is_draft = belum approved.
        DB::table('m_job_positions')->where('status', 'approved')->update(['is_draft' => false]);
    }
};
