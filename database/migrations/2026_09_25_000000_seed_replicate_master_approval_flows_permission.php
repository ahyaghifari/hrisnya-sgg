<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permission "Duplikat Alur Persetujuan" - Shield tidak men-generate verb Replicate, dibuat
 * manual niru pola migration 2026_09_14_000000. Auto-grant ke role yang SUDAH punya
 * Create:MasterApprovalFlows (audiens sama: yang sudah boleh bikin alur baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'Replicate:MasterApprovalFlows', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'Create:MasterApprovalFlows'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama migration permission lain.
    }
};
