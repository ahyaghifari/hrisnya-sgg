<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Lanjutan cleanup permission Shield/custom orphan/dead (instruksi user 2026-08-07):
 *
 * - TrainerScoreRanges/TrainerScoreVariables: 10 verb masing-masing, TIDAK ada
 *   model/Resource/Policy sama sekali di codebase - orphan total.
 * - ViewAny:Schedule, View:Schedule: `SchedulePolicy::viewAny()/view()` ADA dan
 *   mengecek permission ini, TAPI dicek ulang - tidak ada satupun kode (Livewire portal
 *   Jadwal, Filament) yang benar-benar memanggil authorize()/Gate::allows() ke ability
 *   viewAny/view Schedule (portal Jadwal pakai gerbang SISTEM_ABSENSI, bukan Policy ini).
 *   Beda dari Create/Update/Delete:Schedule (dipakai di RoleForm::portalGroups() SISTEM_ABSENSI,
 *   TIDAK disentuh migration ini). Policy class dibiarkan (method viewAny/view mati tapi
 *   tidak berbahaya dibiarkan, bukan scope migration ini).
 * - sistem_master (SISTEM_MASTER, enum case sudah dihapus): dihitung di nav.blade.php
 *   ($canAccessMaster) tapi tidak pernah dipakai gerbang dropdown/menu apapun - dead.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphanIds = Permission::query()
            ->where(function ($q) {
                $q->where('name', 'like', '%:TrainerScoreRanges')
                    ->orWhere('name', 'like', '%:TrainerScoreVariables')
                    ->orWhereIn('name', ['ViewAny:Schedule', 'View:Schedule', 'sistem_master']);
            })
            ->pluck('id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        DB::table('model_has_permissions')->whereIn('permission_id', $orphanIds)->delete();
        DB::table('role_has_permissions')->whereIn('permission_id', $orphanIds)->delete();

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
