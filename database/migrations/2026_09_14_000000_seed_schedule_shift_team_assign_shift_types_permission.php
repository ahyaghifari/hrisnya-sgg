<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permission terpisah buat "atur shift apa saja yang boleh dipakai 1 Tim" (fitur 2026-09-13,
 * checkbox "Shift yang Boleh Dipakai" di modal Tambah/Edit Tim) - SEBELUM ini nebeng ke
 * Create/Update:ScheduleShiftTeam (siapa pun yang boleh bikin/edit nama & warna Tim otomatis
 * juga boleh ganti daftar shift-nya). Dipisah biar bisa digrant beda (instruksi user 2026-09-14) -
 * portal-only, tidak ada Filament Resource buat ScheduleShiftTeam jadi tidak bisa lewat
 * `shield:generate`, dibuat manual di sini niru pola migration 2026_09_12_110000.
 *
 * Auto-grant ke role yang SUDAH punya Update:ScheduleShiftTeam - audiens sama (yang sudah bisa
 * edit Tim), supaya tidak ada yang mendadak kehilangan akses atur shift begitu migration ini
 * jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'AssignShiftTypes:ScheduleShiftTeam', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'Update:ScheduleShiftTeam'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama migration permission lain di modul ini.
    }
};
