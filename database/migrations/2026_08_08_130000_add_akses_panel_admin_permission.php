<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * User::canAccessPanel() sebelumnya `return true` tanpa syarat - SIAPAPUN yang login bisa buka
 * /admin, terlepas role-nya apa. Diperbaiki: role "Admin"/"Super Admin" tetap lolos otomatis
 * (jalur utama, tidak berubah), role lain sekarang butuh permission AKSES_PANEL_ADMIN
 * eksplisit.
 *
 * SENGAJA TIDAK ada safety-net grant di sini - defaultnya harus terkunci (cuma Admin/Super
 * Admin yang tetap bisa masuk /admin seperti sebelumnya, via role bukan permission). Kalau ada
 * role non-Admin yang memang perlu akses /admin (mis. IT Support), assign manual lewat Hak
 * Akses setelah migration ini jalan - jangan di-grant otomatis di sini karena kita sengaja
 * TIDAK tahu siapa yang seharusnya dapat exception ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'akses_panel_admin', 'guard_name' => 'web']);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Permission::where('name', 'akses_panel_admin')->delete();
    }
};
