<?php

use App\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Sinkronkan permission Shield "Verb:Subject" (resource) ke config/filament-shield.php
 * ('policies.methods', sekarang di-trim ke 7 verb: viewAny/view/create/update/delete/
 * restore/forceDelete) - TANPA lewat `shield:generate` (command itu crash discan Page
 * PayrollComponentDetail::getTitle() yang akses property belum di-init di luar mount()).
 *
 * 1) TAMBAH permission yang kurang per Resource aktif (firstOrCreate, aman diulang).
 * 2) HAPUS permission verb "excess" (DeleteAny/ForceDeleteAny/RestoreAny/Replicate/Reorder -
 *    verb yang di-comment di config) — TAPI cuma yang beneran tidak dipakai siapa pun
 *    (tidak nyantol di role_has_permissions maupun model_has_permissions). Yang masih
 *    ke-assign ke role/user SENGAJA dibiarkan, tidak disentuh (instruksi user 2026-08-12).
 *
 * Role "Super Admin" SENGAJA DIABAIKAN dari cek "dipakai" - role itu di-syncPermissions(
 * Permission::all()) di DatabaseSeeder (define_via_gate=false, lihat komentar di sana),
 * jadi otomatis punya SEMUA permission literal termasuk yang sampah. Kalau tidak
 * dikecualikan, tidak ada satu pun excess permission yang pernah kedeteksi "unused".
 */
return new class extends Migration
{
    private const ALL_SHIELD_VERBS = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete',
        'Restore', 'ForceDelete', 'DeleteAny', 'ForceDeleteAny', 'RestoreAny',
        'Replicate', 'Reorder',
    ];

    public function up(): void
    {
        $allowedVerbs = collect(config('filament-shield.policies.methods', []))
            ->map(fn (string $method) => ucfirst($method))
            ->values()
            ->all();

        $excessVerbs = array_values(array_diff(self::ALL_SHIELD_VERBS, $allowedVerbs));

        $subjects = collect(Filament::getPanel('admin')->getResources())
            ->filter(fn ($fqcn) => method_exists($fqcn, 'getModel'))
            ->map(fn ($fqcn) => class_basename($fqcn::getModel()))
            ->unique()
            ->values();

        foreach ($subjects as $subject) {
            foreach ($allowedVerbs as $verb) {
                Permission::firstOrCreate([
                    'name' => "{$verb}:{$subject}",
                    'guard_name' => 'web',
                ]);
            }
        }

        if ($excessVerbs === []) {
            return;
        }

        $excessPermissions = Permission::query()
            ->where(function ($query) use ($subjects, $excessVerbs) {
                foreach ($subjects as $subject) {
                    foreach ($excessVerbs as $verb) {
                        $query->orWhere('name', "{$verb}:{$subject}");
                    }
                }
            })
            ->get();

        $superAdminRoleIds = Role::where('name', 'Super Admin')->pluck('id');

        $deletedIds = [];
        foreach ($excessPermissions as $permission) {
            $isUsed = DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->whereNotIn('role_id', $superAdminRoleIds)
                    ->exists()
                || DB::table('model_has_permissions')->where('permission_id', $permission->id)->exists();

            if ($isUsed) {
                continue;
            }

            $deletedIds[] = $permission->id;
        }

        if ($deletedIds === []) {
            return;
        }

        Permission::query()->whereIn('id', $deletedIds)->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Data cleanup, bukan schema — kalau perlu balikin verb tertentu, generate ulang
        // lewat migration/seeder atau `shield:generate` per-resource.
    }
};
