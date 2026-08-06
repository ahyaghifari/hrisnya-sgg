<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Gerbang menu Tugas (nav baris 1 portal) baru dikasih permission sendiri (`tugas_saya`,
 * CustomPermissionEnum::TUGAS_SAYA) — sebelumnya bebas akses buat siapa saja yang login,
 * sama seperti PELATIHAN_SAYA/PENGAJUAN_ABSENSI_SAYA/COACHING_COUNSELING_SAYA dulu sebelum
 * digerbangi (lihat CustomPermissionsSeeder::bootstrapSelfServicePermissions()).
 *
 * Permission-nya dibuat LANGSUNG di sini (bukan nunggu CustomPermissionsSeeder jalan) dan
 * di-assign ke SEMUA role yang sudah ada saat migration ini jalan, supaya tidak ada role
 * yang tiba-tiba kehilangan akses Tugas begitu gate ini aktif. Sengaja migration (bukan
 * seeder) - deployment/CHECKLIST.md jalankan `migrate --force` sebelum `db:seed
 * CustomPermissionsSeeder`, jadi migration ini tidak boleh bergantung urutan seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'tugas_saya',
            'guard_name' => 'web',
        ]);

        Role::where('guard_name', 'web')->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - menghapus permission ini lewat rollback bisa mengunci
        // semua role dari menu Tugas tanpa sengaja.
    }
};
