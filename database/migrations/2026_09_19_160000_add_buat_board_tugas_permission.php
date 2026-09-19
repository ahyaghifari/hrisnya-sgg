<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Membuat Board baru di modul Tugas sekarang digerbangi permission manual `buat_board_tugas`
 * (CustomPermissionEnum::BUAT_BOARD_TUGAS). Supaya tidak ada role yang tiba-tiba kehilangan
 * kemampuan itu saat gerbang aktif, permission ini di-assign ke SEMUA role yang sudah punya
 * akses menu Tugas (`tugas_saya`) - admin bisa mencabutnya per-role lewat /admin/roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'buat_board_tugas', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'tugas_saya'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission lewat rollback bisa mengunci role dari pembuatan Board.
    }
};
