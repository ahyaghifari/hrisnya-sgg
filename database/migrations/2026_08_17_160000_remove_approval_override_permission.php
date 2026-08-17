<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Fitur "Approve Paksa" (App\Filament\Pages\ApprovalOverride +
 * App\Services\Approval\ApprovalOverrideService) dihapus total (2026-08-17, instruksi user) -
 * gate akses halamannya sebenarnya role literal (Admin/Super Admin via canAccess()), BUKAN
 * permission ini, jadi `View:ApprovalOverride` sudah jadi permission orphan begitu Page-nya
 * dihapus (Filament Page tidak digenerate Shield permission-nya sendiri, kemungkinan tersisa
 * dari eksperimen shield:generate lama). Detach dari semua role lalu hapus baris permission-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::where('name', 'View:ApprovalOverride')->where('guard_name', 'web')->first();

        if (! $permission) {
            return;
        }

        $permission->roles()->detach();
        $permission->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - fitur & Page-nya sudah dihapus total, permission tanpa
        // Page yang menggerbanginya cuma jadi sampah lagi kalau dikembalikan.
    }
};
