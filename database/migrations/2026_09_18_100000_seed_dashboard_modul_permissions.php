<?php

use App\Enum\CustomPermissionEnum;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Gerbang halaman Dashboard per modul (Kepegawaian/Keuangan/Presensi/Sikartap/Rekrutmen) -
 * dulu ikut kebuka otomatis buat siapa saja yang punya akses modul-nya (instruksi user
 * 2026-09-18: mau bisa dimatikan terpisah). Auto-grant ke tiap role yang sudah memegang
 * SISTEM_* modul terkait biar tidak ada yang kehilangan akses dashboard saat gerbang ini
 * mulai dicek (mount()/canAccess() masing2 halaman AND dengan SISTEM_*, lihat
 * CustomPermissionEnum). Idempoten. Pola sama 2026_09_08_150000_seed_sistem_mcu_permission.php.
 */
return new class extends Migration
{
    /** @var array<string,string> dashboard permission -> permission modul terkait */
    private const MAP = [
        CustomPermissionEnum::LIHAT_DASHBOARD_KEPEGAWAIAN->value => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
        CustomPermissionEnum::LIHAT_DASHBOARD_KEUANGAN->value => CustomPermissionEnum::SISTEM_KEUANGAN->value,
        CustomPermissionEnum::LIHAT_DASHBOARD_PRESENSI->value => CustomPermissionEnum::SISTEM_ABSENSI->value,
        CustomPermissionEnum::LIHAT_DASHBOARD_SIKARTAP_MODUL->value => CustomPermissionEnum::SISTEM_SIKARTAP->value,
        CustomPermissionEnum::LIHAT_DASHBOARD_REKRUTMEN->value => CustomPermissionEnum::SISTEM_REKRUTMEN->value,
    ];

    public function up(): void
    {
        $roles = Role::where('guard_name', 'web')->get();

        foreach (self::MAP as $dashboardPermission => $modulePermission) {
            $gate = Permission::firstOrCreate([
                'name' => $dashboardPermission,
                'guard_name' => 'web',
            ]);

            // Permission modul dibuat lewat CustomPermissionsSeeder, bukan migration - di DB
            // kosong (mis. RefreshDatabase di test) barisnya belum ada, hasPermissionTo() akan
            // melempar PermissionDoesNotExist. Belum ada permission = belum ada role yang
            // memegangnya, jadi tidak ada yang perlu di-grant.
            if (! Permission::where('name', $modulePermission)->where('guard_name', 'web')->exists()) {
                continue;
            }

            $roles->filter(fn (Role $role) => $role->hasPermissionTo($modulePermission))
                ->each(fn (Role $role) => $role->givePermissionTo($gate));
        }

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission bisa mengunci role dari halaman.
    }
};
