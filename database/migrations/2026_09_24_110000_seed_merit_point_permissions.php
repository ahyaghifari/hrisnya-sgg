<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Migration seeder untuk permission MeritPointRule, MeritPointTransaction, EmployeeSp, dan SpRecommendation.
 * Memastikan permission Shield-style terdaftar di tabel `permissions` dan di-grant ke Super Admin & Pembinaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = ['MeritPointRule', 'MeritPointTransaction', 'EmployeeSp', 'SpRecommendation'];
        $verbs = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete'];

        $createdPermissions = [];
        foreach ($subjects as $subject) {
            foreach ($verbs as $verb) {
                $createdPermissions[] = Permission::firstOrCreate([
                    'name' => "{$verb}:{$subject}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Grant ke super_admin / Super Admin dan role pemegang sistem_pembinaan_karyawan
        $roles = Role::query()
            ->whereIn('name', ['super_admin', 'Super Admin'])
            ->orWhereHas('permissions', fn ($q) => $q->where('name', 'sistem_pembinaan_karyawan'))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($createdPermissions);
        }

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
    }
};
