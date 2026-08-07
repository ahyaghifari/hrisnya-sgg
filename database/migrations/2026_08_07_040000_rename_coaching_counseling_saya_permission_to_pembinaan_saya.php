<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * CustomPermissionEnum::COACHING_COUNSELING_SAYA -> PEMBINAAN_SAYA (nav "Pembinaan Saya"
 * lebih pas dari "Coaching & Counseling Saya" yang kepanjangan). UPDATE nama baris permission
 * yang sudah ada (bukan hapus+buat baru) supaya role_has_permissions existing tidak lepas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'coaching_counseling_saya')
            ->where('guard_name', 'web')
            ->update(['name' => 'pembinaan_saya']);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Permission::where('name', 'pembinaan_saya')
            ->where('guard_name', 'web')
            ->update(['name' => 'coaching_counseling_saya']);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
