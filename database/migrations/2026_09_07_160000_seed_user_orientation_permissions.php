<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

/**
 * Permission Shield-style untuk UserOrientation - di production belum ke-generate
 * (migration sync_shield_resource_permissions_with_config jalan sebelum resource ini ada).
 * Verb diambil dari config/filament-shield.php biar konsisten kalau daftarnya berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subject = 'UserOrientation';

        $names = collect(config('filament-shield.policies.methods', []))
            ->map(fn (string $m) => ucfirst($m) . ":{$subject}");

        $permissions = $names->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::where('name', 'Super Admin')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->all()));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission bisa mengunci role dari halaman.
    }
};
