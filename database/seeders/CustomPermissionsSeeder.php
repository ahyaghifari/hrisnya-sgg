<?php

namespace Database\Seeders;

use App\Enum\CustomPermissionEnum;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Permission "manual" (bukan format Shield Verb:Subject, tidak disentuh shield:generate) —
 * dipakai buat gerbang akses global ke fitur tertentu (mis. seluruh menu Keuangan di portal
 * karyawan), bukan permission CRUD per-Resource biasa. Assignment ke role dilakukan manual
 * lewat halaman Roles (/admin/roles), seeder ini cuma mastiin baris permission-nya ada.
 *
 * Sengaja TIDAK dipanggil dari DatabaseSeeder — dijalankan manual pas dibutuhkan (termasuk di
 * production, aman idempotent lewat firstOrCreate). Kalau ada permission manual baru ke depannya,
 * tambahkan case-nya di App\Enum\CustomPermissionEnum, lalu jalankan ulang:
 *   php artisan db:seed --class=CustomPermissionsSeeder
 */
class CustomPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CustomPermissionEnum::cases() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }
    }
}
