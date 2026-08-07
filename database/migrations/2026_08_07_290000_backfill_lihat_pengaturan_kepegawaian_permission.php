<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * `LIHAT_PENGATURAN_KEPEGAWAIAN` (enum case) ditambahkan di sesi lain (commit "Hak Akses
 * Penggajian...") tapi tidak pernah ke-seed ke DB (CustomPermissionsSeeder tidak dijalankan
 * ulang saat itu) - nav pwa-nav/kepegawaian-menu.blade.php sudah cek permission ini, tapi
 * baris permission-nya sendiri tidak pernah ada, jadi tombol "Pengaturan Kepegawaian" tidak
 * pernah muncul buat siapa pun (termasuk Super Admin, yang harusnya punya semua permission).
 *
 * Dibuat langsung di sini + digrant ke role yang sudah punya `edit_pengaturan_kepegawaian`
 * (pasangan gerbang EDIT-nya, kalau bisa edit ya wajar juga bisa lihat).
 */
return new class extends Migration
{
    public function up(): void
    {
        $lihat = Permission::firstOrCreate(['name' => 'lihat_pengaturan_kepegawaian', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'edit_pengaturan_kepegawaian'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($lihat));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari halaman Pengaturan Kepegawaian tanpa sengaja.
    }
};
