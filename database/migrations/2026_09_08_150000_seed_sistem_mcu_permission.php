<?php

use App\Enum\CustomPermissionEnum;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Gerbang modul MCU berdiri sendiri (sistem_mcu) — supaya staf klinis yang cuma mengisi hasil
 * MCU tidak perlu diberi SISTEM_SIKARTAP / SISTEM_REKRUTMEN. Auto-grant ke tiap role yang sudah
 * memegang salah satu INPUT_MCU_* / EDIT_INPUT_MCU biar tidak ada yang kehilangan akses saat
 * gerbang ini mulai dicek. Idempoten. Pola sama 2026_09_08_140000_seed_*_permissions.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gate = Permission::firstOrCreate([
            'name' => CustomPermissionEnum::SISTEM_MCU->value,
            'guard_name' => 'web',
        ]);

        $mcuInputPerms = [
            CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value,
            CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value,
            CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value,
            CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value,
            CustomPermissionEnum::EDIT_INPUT_MCU->value,
        ];

        Role::where('guard_name', 'web')->get()
            ->filter(fn (Role $role) => $role->hasAnyPermission($mcuInputPerms))
            ->each(fn (Role $role) => $role->givePermissionTo($gate));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission bisa mengunci role dari halaman.
    }
};
