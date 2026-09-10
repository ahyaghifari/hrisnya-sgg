<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Bubarkan grup "Approver" di tree /hak-akses. Setelah dicek:
 * - `approve_pengajuan_absensi` — nol referensi di logika. Izin/cuti/lembur (attendance_requests)
 *   sepenuhnya ditentukan approval flow (MasterApprovalFlows step → resolusi approver by
 *   jabatan/atasan/user), bukan permission ini.
 * - `approve_manajemen_lowongan_kerja` — mati di HRIS, modul Rekrutmen sudah app terpisah.
 * - `sistem_approval` — gerbang grup "Approver" itu sendiri, cuma dipakai RoleForm buat
 *   pengelompokan, tidak pernah digate di kode.
 *
 * `approve_pengajuan_pelatihan` TIDAK dihapus — masih dicek literal di CourseUserService
 * (approve/reject enrollment LMS), pindah ke grup Sistem LMS di RoleForm.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permission::query()
            ->whereIn('name', [
                'approve_pengajuan_absensi',
                'approve_manajemen_lowongan_kerja',
                'sistem_approval',
            ])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // model_has_permissions tidak lagi FK-cascade ke permissions (lihat
        // 2025_09_11_114601_update_spatie_permission_for_ulid.php) — hapus manual;
        // role_has_permissions masih cascade.
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();

        Permission::query()->whereIn('id', $ids)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse — pola sama cleanup_orphaned_shield_permissions.
    }
};
