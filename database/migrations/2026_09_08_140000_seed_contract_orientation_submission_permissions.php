<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permission Shield-style untuk ContractSubmission (pengajuan perpanjangan kontrak) &
 * OrientationSubmission (pengajuan penilaian orientasi) — dipastikan ada supaya muncul di
 * tree Hak Akses / form Role. Verb diambil dari config/filament-shield.php biar konsisten.
 * Idempoten (firstOrCreate) — aman dijalankan ulang. Pola sama
 * 2026_09_07_160000_seed_user_orientation_permissions.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = ['ContractSubmission', 'OrientationSubmission'];
        $methods = collect(config('filament-shield.policies.methods', [
            'viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete',
        ]));

        $permissions = collect($subjects)
            ->flatMap(fn (string $subject) => $methods->map(fn (string $m) => ucfirst($m) . ":{$subject}"))
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::where('name', 'Super Admin')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->all()));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission bisa mengunci role dari halaman.
    }
};
