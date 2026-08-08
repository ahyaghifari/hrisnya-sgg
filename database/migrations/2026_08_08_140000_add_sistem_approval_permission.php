<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Grup portal baru "Sistem Approval" (RoleForm::portalGroups()) - wadah buat permission
 * approve lintas-modul (APPROVE_PENGAJUAN_ABSENSI, APPROVE_MANAJEMEN_LOWONGAN_KERJA) yang
 * sebelumnya tidak ada di grup manapun (nyasar ke bucket "Lainnya"). Safety net: role yang
 * sudah pegang salah satu permission approve tsb otomatis dapat gerbang coarse-nya juga,
 * supaya tidak ada yang kehilangan akses saat gerbang ini mulai dicek.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sistemApproval = Permission::firstOrCreate(['name' => 'sistem_approval', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->whereIn('name', [
            'approve_pengajuan_absensi',
            'approve_manajemen_lowongan_kerja',
        ]))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($sistemApproval));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari fitur approval tanpa sengaja.
    }
};
