<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Permission PascalCase yatim (dibuat 2026-07-14/08-06 sebelum penamaan snake_case, muncul di
 * bucket "Lainnya" di /hak-akses). Nol referensi di kode - semua pengecekan pakai padanan
 * snake_case yang masih hidup:
 * - ApprovePengajuanAbsensi / ApproveManajemenLowonganKerja: konsepnya sudah dibuang
 *   (lihat drop_approver_group_permissions).
 * - ApprovePengajuanPelatihan = approve_pengajuan_pelatihan
 * - ImportDataAbsensi = sync_data_absensi
 * - ViewReportDataAbsensi = view_report_data_absensi
 * - LihatPerusahaanLain = lihat_perusahaan_lain
 *
 * Plus `edit_pengaturan_tugas` (2026-09-21): halaman /tugas/pengaturan (TaskSettingsPage) dihapus,
 * satu-satunya settingnya (task_require_approval_default) tidak dipakai form Tugas yang baru -
 * baris settings module 'task' ikut dibersihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('module', 'task')->delete();

        $ids = Permission::query()
            ->whereIn('name', [
                'ApprovePengajuanAbsensi',
                'ApproveManajemenLowonganKerja',
                'ApprovePengajuanPelatihan',
                'ImportDataAbsensi',
                'ViewReportDataAbsensi',
                'LihatPerusahaanLain',
                'edit_pengaturan_tugas',
            ])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();

        Permission::query()->whereIn('id', $ids)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama drop_approver_group_permissions.
    }
};
