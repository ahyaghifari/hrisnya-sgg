<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * MeritPointTransactionResource dihapus (instruksi user 2026-09-27) - 7 verb Shield
 * (ViewAny/View/Create/Update/Delete/Restore/ForceDelete:MeritPointTransaction, dibuat migration
 * 2026_09_24_110000_seed_merit_point_permissions) diganti 1 permission custom BERI_MERIT_POINT.
 * MeritPointRule TIDAK disentuh - rule tetap Shield biasa (7 verb), sekarang 1 Fieldset gabungan
 * dengan BERI_MERIT_POINT di Hak Akses (lihat RoleForm::customPermissionSubjectOverrides()).
 *
 * Role yang sebelumnya bisa memberi poin (pemegang Create:MeritPointTransaction, ATAU role
 * pemegang sistem_pembinaan_karyawan - fallback lama EmployeeStatistics::
 * getCanCreateMeritTransactionProperty()) di-grant BERI_MERIT_POINT supaya tidak kehilangan akses
 * diam-diam. Super Admin digrant eksplisit (define_via_gate=false, tidak bypass otomatis).
 */
return new class extends Migration
{
    public function up(): void
    {
        $beriMeritPoint = Permission::firstOrCreate(['name' => 'beri_merit_point', 'guard_name' => 'web']);

        $roles = Role::query()
            ->whereIn('name', ['super_admin', 'Super Admin'])
            ->orWhereHas('permissions', fn ($q) => $q->whereIn('name', ['Create:MeritPointTransaction', 'sistem_pembinaan_karyawan']))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($beriMeritPoint);
        }

        DB::table('permissions')->where('name', 'like', '%:MeritPointTransaction')->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - permission MeritPointTransaction lama sudah tidak dipakai
        // kode apa pun (Resource-nya dihapus di commit yang sama), membangunkan lagi cuma bikin
        // permission yatim. BERI_MERIT_POINT juga sengaja dibiarkan (mencabut lewat rollback
        // berisiko mengunci role dari fitur beri poin tanpa sengaja).
    }
};
