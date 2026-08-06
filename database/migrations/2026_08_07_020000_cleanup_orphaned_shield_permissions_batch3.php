<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Lanjutan batch 1 & 2 — ManPowerPlaningCluster & UserTransitionsCluster (file Cluster-nya
 * dihapus 2026-08-07, instruksi user "cluster2 buka aja, ngga perlu ada cluster", Resource
 * di dalamnya di-unwrap jadi navigationGroup 'Kepegawaian' langsung) jadi orphan penuh —
 * permission `View:*Cluster` ini generate otomatis per Cluster class, sekarang class-nya
 * sudah tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = [
            'ManPowerPlaningCluster',
            'UserTransitionsCluster',
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
