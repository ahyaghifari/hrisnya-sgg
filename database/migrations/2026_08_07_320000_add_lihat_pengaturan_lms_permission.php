<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Pengaturan LMS (`/pelatihan/pengaturan`) - dulu numpang SISTEM_LMS buat lihat DAN
 * EDIT_PENGATURAN_LMS buat simpan. Dipisah jadi 2 gerbang murni (LIHAT_PENGATURAN_LMS/
 * EDIT_PENGATURAN_LMS), sama pola LIHAT_PENGATURAN_ABSENSI/LIHAT_PENGATURAN_KEPEGAWAIAN.
 * Route-nya juga baru didaftarkan ke portal-pages.php (sebelumnya lolos tanpa cek
 * middleware, gate murni di mount()).
 *
 * Permission dibuat langsung di sini + digrant ke role yang sudah punya
 * edit_pengaturan_lms (pasangan gerbang EDIT-nya, kalau bisa edit ya wajar juga bisa lihat).
 */
return new class extends Migration
{
    public function up(): void
    {
        $lihat = Permission::firstOrCreate(['name' => 'lihat_pengaturan_lms', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'edit_pengaturan_lms'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($lihat));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari halaman Pengaturan LMS tanpa sengaja.
    }
};
