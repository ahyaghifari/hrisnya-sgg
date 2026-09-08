<?php

/**
 * Bersihkan "custom permission" ganda / yatim.
 *
 * Cara pakai:
 *   1. php artisan tinker path/ke/cleanup_perms.php   -> DRY RUN (cuma menampilkan rencana)
 *   2. set $APPLY = true di bawah, jalankan lagi        -> eksekusi
 *
 * Logika:
 *   - "custom permission" = nama TANPA format Verb:Subject (bukan permission resource Shield).
 *   - "resmi" = ada di App\Enum\CustomPermissionEnum, App\Enum\WidgetPermissionEnum,
 *               atau config('filament-shield.custom_permissions').
 *   - "yatim" = custom permission yang BUKAN resmi -> tidak pernah dicek kode -> dihapus.
 *   - Sebelum hapus, assignment role/user yatim DIPINDAH ke permission resmi penggantinya:
 *       * StudlyCase  -> Str::snake()  (mis. ApprovePengajuanAbsensi -> approve_pengajuan_absensi)
 *       * rename manual -> lihat $RENAME_MAP (SESUAIKAN setelah lihat output audit di prod).
 *   - Yatim yang penggantinya tidak ketemu -> TIDAK disentuh, cuma dilaporkan.
 */

use App\Enum\CustomPermissionEnum;
use App\Enum\WidgetPermissionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

$APPLY = false; // <-- ubah ke true untuk benar-benar mengeksekusi

/** Rename non-mekanis: [nama_yatim => nama_pengganti_resmi | null]. null = hapus tanpa pindah assignment. */
$RENAME_MAP = [
    'lihat_semua_perusahaan' => 'lihat_perusahaan_lain',
    'lihat_dashboard_sikartap_butuh_tindakan' => 'lihat_dashboard_sikartap_antrian_tahap',
];

$known = collect(CustomPermissionEnum::cases())
    ->merge(WidgetPermissionEnum::cases())
    ->map(fn ($c) => $c->value)
    ->merge(config('filament-shield.custom_permissions', []))
    ->unique()->flip();

$orphans = Permission::query()
    ->where('name', 'not like', '%:%')
    ->get()
    ->reject(fn (Permission $p) => $known->has($p->name));

echo ($APPLY ? '### MODE: APPLY ###' : '### MODE: DRY RUN (tidak ada yang diubah) ###') . PHP_EOL . PHP_EOL;

$deleted = 0;
$moved = 0;
$skipped = [];

foreach ($orphans as $orphan) {
    $target = array_key_exists($orphan->name, $RENAME_MAP)
        ? $RENAME_MAP[$orphan->name]
        : ($orphan->name !== Str::snake($orphan->name) ? Str::snake($orphan->name) : null);

    $roleIds = DB::table('role_has_permissions')->where('permission_id', $orphan->id)->pluck('role_id');
    $userRows = DB::table('model_has_permissions')->where('permission_id', $orphan->id)->get();

    $targetPerm = $target ? Permission::where('name', $target)->where('guard_name', $orphan->guard_name)->first() : null;

    if ($target !== null && ! $targetPerm) {
        $skipped[] = "{$orphan->name}  (pengganti '{$target}' tidak ditemukan)";
        continue;
    }

    $note = $target === null
        ? 'HAPUS (tidak ada pengganti; ' . $roleIds->count() . ' role + ' . $userRows->count() . ' user assignment ikut hilang)'
        : "pindah {$roleIds->count()} role + {$userRows->count()} user assignment -> '{$target}', lalu HAPUS";

    echo "- {$orphan->name}  [{$orphan->guard_name}]  =>  {$note}" . PHP_EOL;

    if (! $APPLY) {
        continue;
    }

    if ($targetPerm) {
        foreach ($roleIds as $rid) {
            DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $targetPerm->id, 'role_id' => $rid], []);
            $moved++;
        }
        foreach ($userRows as $row) {
            DB::table('model_has_permissions')->updateOrInsert(
                ['permission_id' => $targetPerm->id, 'model_type' => $row->model_type, 'model_id' => $row->model_id],
                []
            );
            $moved++;
        }
    }

    $orphan->delete();
    $deleted++;
}

if ($skipped) {
    echo PHP_EOL . '# DILEWATI (tinjau manual, tambahkan ke $RENAME_MAP kalau perlu):' . PHP_EOL;
    foreach ($skipped as $s) {
        echo "  - {$s}" . PHP_EOL;
    }
}

if ($APPLY) {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    echo PHP_EOL . "SELESAI: {$deleted} permission dihapus, {$moved} assignment dipindah. Cache di-flush." . PHP_EOL;
} else {
    echo PHP_EOL . 'DRY RUN selesai. Set $APPLY = true untuk eksekusi.' . PHP_EOL;
}
