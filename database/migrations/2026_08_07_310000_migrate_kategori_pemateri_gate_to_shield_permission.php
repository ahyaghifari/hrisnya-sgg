<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Kategori Pelatihan (LmsCategoriesPage) & Pemateri (TrainersPage/TrainerDetailPage) -
 * Resource+Policy+permission Shield sudah lama ada (Create/Update/Delete di kedua halaman
 * malah sudah pakai Policy dari awal), cuma mount() (ViewAny/View) yang masih numpang
 * SISTEM_LMS. Diganti murni ke Policy/permission Shield. Nav link "Kategori Pelatihan" &
 * "Pemateri" sebelumnya juga tanpa gerbang sama sekali (desktop & mobile).
 *
 * Grant ViewAny:LmsCategories, ViewAny/View:Trainers ke role yang sudah punya sistem_lms -
 * safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny:LmsCategories', 'ViewAny:Trainers', 'View:Trainers'];
        $permissions = Permission::whereIn('name', $names)->get();

        if ($permissions->count() !== count($names)) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_lms'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari menu Kategori/Pemateri tanpa sengaja.
    }
};
