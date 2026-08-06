<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Hapus permission Shield yang subject-nya sudah tidak punya Resource/Cluster/Widget sama
 * sekali di codebase — ketauan pas bikin halaman portal /hak-akses (tree permission per
 * role), muncul sebagai baris ungrouped/nyampur karena Shield generate tidak pernah
 * membersihkan permission lama pas Resource-nya dihapus/direname.
 *
 * Sudah dicek satu-satu manual (bukan tebakan):
 * - Banks/TaskService/LmsCoursePlans/LmsCoursePlanItems: 10 baris masing-masing (format
 *   lama sebelum verb ditrim di config/filament-shield.php), TIDAK ADA Resource-nya sama
 *   sekali di app/Filament/Resources.
 * - AssessmentCluster/MasterSalaryComponentCluster: file cluster-nya lagi dihapus di sesi
 *   ini (lihat commit-commit terkait cluster Assessment/MasterSalaryComponent).
 * - ShiftPatternCluster/EmployeeDocumentCluster: file cluster-nya sudah lama tidak ada di
 *   codebase sama sekali.
 *
 * Subject SENGAJA tidak termasuk ManPowerPlaningCluster/UserTransitionsCluster - cluster
 * itu masih aktif (file-nya masih ada), permission View:*Cluster-nya legit walau jarang
 * dicentang manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = [
            'Banks',
            'TaskService',
            'LmsCoursePlans',
            'LmsCoursePlanItems',
            'AssessmentCluster',
            'MasterSalaryComponentCluster',
            'ShiftPatternCluster',
            'EmployeeDocumentCluster',
        ];

        $orphanIds = Permission::query()
            ->where(function ($q) use ($subjects) {
                foreach ($subjects as $subject) {
                    $q->orWhere('name', 'like', "%:{$subject}");
                }
            })
            ->pluck('id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        // model_has_permissions tidak lagi punya FK cascade ke permissions (lihat
        // 2025_09_11_114601_update_spatie_permission_for_ulid.php), jadi hapus manual;
        // role_has_permissions masih FK cascade.
        DB::table('model_has_permissions')->whereIn('permission_id', $orphanIds)->delete();

        Permission::query()->whereIn('id', $orphanIds)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - permission orphan yang sudah dihapus tidak boleh
        // muncul lagi lewat rollback (pola sama 2026_07_11_195830).
    }
};
