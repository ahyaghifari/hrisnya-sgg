<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Lanjutan 2026_08_07_000000_cleanup_orphaned_shield_permissions.php — subject yang
 * baru ketauan orphan setelah investigasi lanjutan (instruksi user: hilangkan Task dari
 * Manajemen Organisasi, Master Shift, Extra Hour Allowance Rules, Performance Chart):
 *
 * - Task: TaskRequestResource (Filament admin) dihapus total - dicek dulu app/Policies/Task/
 *   TaskPolicy.php (target model App\Models\Task\Task, model Kanban asli) TIDAK dipanggil
 *   di mana pun (grep authorize()/can('...Task') di seluruh app/Livewire kosong) - permission
 *   ViewAny/View/Create/Update/Delete:Task ini cuma buat admin Resource yang baru dihapus,
 *   BUKAN dipakai otorisasi Kanban asli (Kanban pakai cek kepemilikan board/member sendiri,
 *   bukan Shield policy). Aman dihapus.
 * - MasterShift: model ada (tabel m_shifts) tapi tidak ada Resource/Livewire yang
 *   mengelolanya sama sekali.
 * - ExtraHourAllowanceRules: Resource-nya sudah dihapus di sesi ini juga.
 * - PerformanceChart: Widget filenya dihapus (tidak pernah terdaftar di
 *   AdminPanelProvider->widgets(), sudah dead sebelum dihapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = [
            'Task',
            'MasterShift',
            'ExtraHourAllowanceRules',
            'PerformanceChart',
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
