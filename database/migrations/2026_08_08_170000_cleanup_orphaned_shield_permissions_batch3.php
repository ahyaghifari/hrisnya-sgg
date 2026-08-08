<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Lanjutan batch cleanup permission Shield orphan (lihat
 * 2026_08_07_010000_cleanup_orphaned_shield_permissions_batch2.php) — subject yang dihapus
 * sesi ini: komponen payroll KELEBIHAN_JAM_KERJA dibongkar total (calculator, enum case,
 * ExtraHourAllowanceRules, AttendancePolicies — sudah lama tidak dipakai kalkulasi, digantikan
 * Setting::resolve()) dan ServiceFeePool (model tanpa Resource/service/pemakai sama sekali,
 * kemungkinan rencana lama yang tidak pernah dieksekusi).
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = [
            'AttendancePolicies',
            'ServiceFeePool',
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

        DB::table('model_has_permissions')->whereIn('permission_id', $orphanIds)->delete();

        Permission::query()->whereIn('id', $orphanIds)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama migration cleanup sebelumnya.
    }
};
