<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Halaman Pelatihan portal (LmsCoursesPage/CreateLmsCourse/LmsCourseDetailPage) -
 * LmsCoursesResource (admin) + LmsCoursesPolicy + permission Shield sudah lama ada, tapi
 * portalnya numpang SISTEM_LMS di ketiganya (CreateLmsCourse malah dobel - SISTEM_LMS +
 * Policy create sekaligus). Diganti murni ke Policy/permission Shield
 * (ViewAny/Create/View:LmsCourses). Nav link "Pelatihan" sebelumnya juga tanpa gerbang sama
 * sekali (desktop & mobile) - sekarang digerbangi ViewAny:LmsCourses juga.
 *
 * EDIT_DETAIL_KURSUS_LMS & APPROVE_PENGAJUAN_PELATIHAN (permission granular lain di
 * LmsCourseDetailPage) TIDAK disentuh - itu memang sengaja dipisah dari Update:LmsCourses.
 *
 * Grant ViewAny/Create/View:LmsCourses ke role yang sudah punya sistem_lms - safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny:LmsCourses', 'Create:LmsCourses', 'View:LmsCourses'];
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
        // dari menu Pelatihan tanpa sengaja.
    }
};
