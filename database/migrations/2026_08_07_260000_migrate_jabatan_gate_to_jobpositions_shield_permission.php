<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Halaman Jabatan portal (JabatanPage/CreateJabatanPage "Ajukan Jabatan Baru"/EditJabatanPage)
 * - JobPositionResource (admin, Master & ManPower dua-duanya) + JobPositionsPolicy + permission
 * Shield sudah lama ada, tapi portalnya numpang SISTEM_KEPEGAWAIAN doang, tidak pernah pakai
 * Policy sama sekali. Diganti murni ke Policy/permission Shield JobPositions. Nav link "Jabatan"
 * sebelumnya juga tanpa gerbang sama sekali (desktop & mobile) - sekarang digerbangi
 * ViewAny:JobPositions juga.
 *
 * Grant ViewAny/Create/Update:JobPositions ke role yang sudah punya sistem_kepegawaian -
 * safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny:JobPositions', 'Create:JobPositions', 'Update:JobPositions'];
        $permissions = Permission::whereIn('name', $names)->get();

        if ($permissions->count() !== count($names)) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_kepegawaian'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari menu Jabatan tanpa sengaja.
    }
};
