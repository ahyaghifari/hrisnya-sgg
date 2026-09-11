<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * ScheduleShiftTeam ("Regu") tidak punya Filament Resource (portal-only, tab "Regu" di
 * ScheduleDetailPage - lihat ScheduleShiftTeamPolicy) jadi permission Shield-style-nya tidak
 * otomatis ke-generate lewat `shield:generate --resource=X` - dibuat manual di sini, pola sama
 * migration 2026_08_17_150100_seed_extra_shift_rule_permissions. Dikelompokkan ke section
 * "Jadwal Shift" di /hak-akses & Role form (lihat RoleForm::subjectContainerOverrides()).
 *
 * Digrant ke role yang sudah punya Update:ShiftScheduleAssignments (Kepala Ruangan/pengelola
 * roster) - audiens yang sama, supaya yang sudah bisa isi jadwal langsung bisa kelola regu
 * juga tanpa perlu di-assign ulang manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];
        $permissions = collect($names)->mapWithKeys(
            fn (string $action) => [$action => Permission::firstOrCreate(['name' => "{$action}:ScheduleShiftTeam", 'guard_name' => 'web'])]
        );

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'Update:ShiftScheduleAssignments'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->values()->all()));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama migration permission lain di modul ini.
    }
};
