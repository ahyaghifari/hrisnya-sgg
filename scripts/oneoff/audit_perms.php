<?php

use App\Enum\CustomPermissionEnum;
use App\Enum\WidgetPermissionEnum;
use Illuminate\Support\Facades\DB;

$known = collect(CustomPermissionEnum::cases())
    ->merge(WidgetPermissionEnum::cases())
    ->map(fn ($c) => $c->value)
    ->merge(config('filament-shield.custom_permissions', []))
    ->unique()->values();

// "custom" = nama TIDAK berformat Verb:Subject (Shield). Sisanya permission resource biasa.
$custom = DB::table('permissions')->where('name', 'not like', '%:%')->orderBy('name')->get(['id', 'name', 'guard_name']);

echo '== RINGKAS ==' . PHP_EOL;
echo '  total permission           : ' . DB::table('permissions')->count() . PHP_EOL;
echo '  custom (non Verb:Subject)  : ' . $custom->count() . PHP_EOL;
echo '  didefinisikan di kode      : ' . $known->count() . PHP_EOL;

echo PHP_EOL . '== DUPLIKAT NAMA (lintas guard) ==' . PHP_EOL;
$dups = DB::table('permissions')->select('name', DB::raw('count(*) c'))->groupBy('name')->havingRaw('count(*) > 1')->get();
if ($dups->isEmpty()) {
    echo '  (tidak ada)' . PHP_EOL;
}
foreach ($dups as $d) {
    foreach (DB::table('permissions')->where('name', $d->name)->get(['id', 'guard_name']) as $row) {
        $r = DB::table('role_has_permissions')->where('permission_id', $row->id)->count();
        $u = DB::table('model_has_permissions')->where('permission_id', $row->id)->count();
        echo sprintf('  %-42s id=%s guard=%-8s role=%d user=%d', $d->name, $row->id, $row->guard_name, $r, $u) . PHP_EOL;
    }
}

echo PHP_EOL . '== CUSTOM PERMISSION YATIM (tidak ada di CustomPermissionEnum / WidgetPermissionEnum / filament-shield.custom_permissions) ==' . PHP_EOL;
$orphans = $custom->reject(fn ($p) => $known->contains($p->name));
if ($orphans->isEmpty()) {
    echo '  (tidak ada — semua custom permission terpakai di kode)' . PHP_EOL;
}
foreach ($orphans as $p) {
    $r = DB::table('role_has_permissions')->where('permission_id', $p->id)->count();
    $u = DB::table('model_has_permissions')->where('permission_id', $p->id)->count();
    echo sprintf('  %-42s id=%s guard=%-8s role=%d user=%d', $p->name, $p->id, $p->guard_name, $r, $u) . PHP_EOL;
}
echo PHP_EOL . 'total yatim: ' . $orphans->count() . PHP_EOL;
