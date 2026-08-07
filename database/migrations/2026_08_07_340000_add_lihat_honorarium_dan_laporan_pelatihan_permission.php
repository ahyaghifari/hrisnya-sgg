<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Master Honorarium (`/pelatihan/honorarium`) & Laporan Pelatihan (`/pelatihan/laporan`) -
 * dulu numpang SISTEM_LMS buat lihat. Dipisah jadi LIHAT_HONORARIUM_LMS (pasangan
 * EDIT_HONORARIUM_LMS yang sudah ada) dan LIHAT_LAPORAN_PELATIHAN (murni read-only, tanpa
 * pasangan EDIT - tidak ada aksi tulis di halaman laporan).
 *
 * Permission dibuat langsung di sini + digrant ke role yang sudah punya sistem_lms
 * (role yang sebelumnya bisa akses kedua halaman ini lewat gate lama, supaya tidak ada
 * yang kehilangan akses).
 */
return new class extends Migration
{
    public function up(): void
    {
        $lihatHonorarium = Permission::firstOrCreate(['name' => 'lihat_honorarium_lms', 'guard_name' => 'web']);
        $lihatLaporan = Permission::firstOrCreate(['name' => 'lihat_laporan_pelatihan', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_lms'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$lihatHonorarium, $lihatLaporan]));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari halaman Honorarium/Laporan Pelatihan tanpa sengaja.
    }
};
