<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Job Evaluation (`/kepegawaian/job-evaluation`) murni konfigurasi (edit job_evaluation_score
 * per Jabatan) - bukan CRUD JobPositions, jadi dapat permission sendiri (LIHAT_JOB_EVALUATION/
 * KELOLA_JOB_EVALUATION), bukan numpang ViewAny/Update:JobPositions. Dulu numpang
 * SISTEM_KEPEGAWAIAN buat lihat DAN simpan (saveJe() sebelumnya malah tanpa cek permission
 * apapun - siapa saja yang bisa buka halaman bisa mengubah skor).
 *
 * Permission dibuat langsung di sini (bukan nunggu CustomPermissionsSeeder), lalu:
 * - LIHAT_JOB_EVALUATION digrant ke role yang sudah punya sistem_kepegawaian (safety net akses
 *   baca, sama seperti sebelumnya).
 * - KELOLA_JOB_EVALUATION JUGA digrant ke role yang sama - sebelumnya siapa saja yang bisa
 *   buka halaman otomatis bisa simpan (tidak ada gerbang), jadi safety net-nya ikut yang sama
 *   biar tidak ada yang tiba-tiba kehilangan kemampuan yang sudah mereka pakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lihat = Permission::firstOrCreate(['name' => 'lihat_job_evaluation', 'guard_name' => 'web']);
        $kelola = Permission::firstOrCreate(['name' => 'kelola_job_evaluation', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_kepegawaian'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$lihat, $kelola]));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari fitur Job Evaluation tanpa sengaja.
    }
};
