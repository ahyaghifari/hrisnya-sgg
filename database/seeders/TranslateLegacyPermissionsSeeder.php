<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Migrasi data satu kali: sebelum 2026_07_11_195830_remove_legacy_snake_case_permissions.php
 * menghapus semua permission snake_case, role production sudah punya ~2800 assignment pakai
 * nama lama itu (lihat database/seeders/data/legacy_role_permissions_snapshot.json, diambil
 * 2026-07-14 sebelum migrate dijalankan ulang). Seeder ini "menerjemahkan" assignment lama itu
 * ke permission Shield yang setara (database/seeders/data/legacy_permission_shield_map.php),
 * supaya role yang sudah dikonfigurasi manual tidak kembali ke 0 permission gara-gara migrasi.
 *
 * URUTAN WAJIB: migrate --force -> shield:generate -> db:seed --class=CustomPermissionsSeeder
 * -> db:seed --class=TranslateLegacyPermissionsSeeder (seeder ini, paling akhir, butuh katalog
 * permission Shield + custom sudah lengkap dulu).
 *
 * Aman dijalankan ulang (idempotent, givePermissionTo tidak duplikat). TIDAK assign role baru,
 * cuma isi ulang permission utuk role yang SUDAH ADA dari snapshot.
 */
class TranslateLegacyPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $snapshotPath = database_path('seeders/data/legacy_role_permissions_snapshot.json');
        $mapPath = database_path('seeders/data/legacy_permission_shield_map.php');

        if (! file_exists($snapshotPath)) {
            $this->command?->warn('Snapshot tidak ditemukan, skip: '.$snapshotPath);

            return;
        }

        $snapshot = json_decode(file_get_contents($snapshotPath), true);
        $map = require $mapPath;

        $existingPermissionNames = Permission::pluck('id', 'name');

        $grantedCount = 0;
        $skippedNoMapping = [];
        $skippedMissingTarget = [];

        // Kelompokkan per role supaya sekali query per role (bukan per baris)
        $byRole = collect($snapshot)->groupBy('role_id');

        foreach ($byRole as $roleId => $rows) {
            $permissionIdsToGrant = [];

            foreach ($rows as $row) {
                $legacyName = $row['permission_name'];
                $target = $map[$legacyName] ?? null;

                if ($target === null) {
                    $skippedNoMapping[$legacyName] = true;

                    continue;
                }

                if (! isset($existingPermissionNames[$target])) {
                    $skippedMissingTarget[$target] = true;

                    continue;
                }

                $permissionIdsToGrant[] = $existingPermissionNames[$target];
            }

            if (empty($permissionIdsToGrant)) {
                continue;
            }

            $permissionIdsToGrant = array_unique($permissionIdsToGrant);

            // Insert langsung ke pivot, skip yang sudah ada - lebih cepat dari syncPermissions
            // (yang akan REPLACE semua permission role, bukan cuma nambah) untuk ribuan baris.
            $roleExists = DB::table('roles')->where('id', $roleId)->exists();

            if (! $roleExists) {
                continue;
            }

            $alreadyGranted = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $permissionIdsToGrant)
                ->pluck('permission_id')
                ->all();

            $toInsert = array_diff($permissionIdsToGrant, $alreadyGranted);

            if (! empty($toInsert)) {
                DB::table('role_has_permissions')->insert(
                    array_map(fn ($permissionId) => [
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ], $toInsert)
                );
                $grantedCount += count($toInsert);
            }
        }

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));

        $this->command?->info("Permission Shield di-grant: {$grantedCount} baris baru ke ".$byRole->count().' role.');

        if (! empty($skippedNoMapping)) {
            $this->command?->warn('Legacy permission tanpa mapping (dilewati, lihat legacy_permission_shield_map.php): '.implode(', ', array_keys($skippedNoMapping)));
        }

        if (! empty($skippedMissingTarget)) {
            $this->command?->error('Target Shield permission dari mapping TIDAK DITEMUKAN di DB (cek shield:generate sudah jalan?): '.implode(', ', array_keys($skippedMissingTarget)));
        }
    }
}
