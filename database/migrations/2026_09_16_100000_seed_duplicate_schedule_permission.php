<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Tombol "Duplikat" di halaman Jadwal (SchedulesPage) dulu numpang cek Create:Schedule - dipisah
 * jadi permission sendiri (instruksi user 2026-09-16, lihat SchedulePolicy::duplicate()). Nama
 * "Duplicate:Schedule" ikut pola Verb:Subject standar Shield, jadi otomatis ke-grup ke node
 * "Schedule" yang sudah ada di /hak-akses & Role form tanpa perlu listing manual tambahan.
 *
 * Digrant ke role yang sudah punya Create:Schedule (audiens sama - yang sebelumnya memang
 * bisa duplikat lewat izin itu tetap bisa, tidak ada yang tiba-tiba kehilangan akses).
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicate = Permission::firstOrCreate(['name' => 'Duplicate:Schedule', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'Create:Schedule'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($duplicate));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // yang sudah dikonfigurasi manual sejak permission ini seed, sama pola migration
        // permission lain di modul ini (mis. 2026_08_17_150100_seed_extra_shift_rule_permissions).
    }
};
