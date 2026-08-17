<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * ExtraShiftRule tidak punya Filament Resource (portal-only, lihat ExtraShiftRulePolicy) jadi
 * permission Shield-style-nya tidak otomatis ke-generate lewat `shield:generate --resource=X`
 * seperti biasa - dibuat manual di sini, lalu digrant ke role yang sudah punya permission
 * ExtraShiftAssignment (sibling fitur, audience sama: yang boleh kelola extra shift).
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAny = Permission::firstOrCreate(['name' => 'ViewAny:ExtraShiftRule', 'guard_name' => 'web']);
        $create = Permission::firstOrCreate(['name' => 'Create:ExtraShiftRule', 'guard_name' => 'web']);
        $delete = Permission::firstOrCreate(['name' => 'Delete:ExtraShiftRule', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'ViewAny:ExtraShiftAssignment'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($viewAny));

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'Create:ExtraShiftAssignment'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($create));

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'Delete:ExtraShiftAssignment'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($delete));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari halaman Aturan Extra Shift tanpa sengaja.
    }
};
