<?php

namespace Database\Seeders;

use App\Enum\WidgetPermissionEnum;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Sibling CustomPermissionsSeeder, khusus WidgetPermissionEnum - dipisah supaya permission
 * widget Dashboard tidak numpang di seeder permission umum, konsisten dengan alasan
 * WidgetPermissionEnum dipisah dari CustomPermissionEnum (lihat docblock enum itu). Standalone
 * (TIDAK dipanggil dari DatabaseSeeder::run(), sama seperti CustomPermissionsSeeder) - jalankan
 * manual tiap ada case baru: `php artisan db:seed --class=WidgetPermissionSeeder`.
 */
class WidgetPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (WidgetPermissionEnum::cases() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }
    }
}
