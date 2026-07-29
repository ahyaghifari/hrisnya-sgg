<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * SISTEM_REKRUTMEN dihapus dari CustomPermissionEnum, digabung ke SISTEM_KEPEGAWAIAN (modul
 * Rekrutmen sekarang gerbangnya ikut Kepegawaian, lihat routes/web.php & nav.blade.php). Role
 * (dan user, kalau ada yang di-assign langsung) yang sebelumnya punya sistem_rekrutmen diberi
 * sistem_kepegawaian dulu SEBELUM permission lama dihapus, supaya tidak ada yang mendadak
 * kehilangan akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rekrutmen = Permission::where('name', 'sistem_rekrutmen')->where('guard_name', 'web')->first();

        if (! $rekrutmen) {
            return;
        }

        $kepegawaian = Permission::firstOrCreate([
            'name' => 'sistem_kepegawaian',
            'guard_name' => 'web',
        ]);

        $rekrutmen->roles->each(fn ($role) => $role->givePermissionTo($kepegawaian));
        $rekrutmen->users->each(fn ($user) => $user->givePermissionTo($kepegawaian));

        // Cascade delete bawaan tabel pivot Spatie (role_has_permissions/model_has_permissions)
        // otomatis bersihin assignment lama begitu baris permission ini dihapus.
        $rekrutmen->delete();
    }

    public function down(): void
    {
        // Data cleanup, bukan schema — kalau perlu balikin, generate ulang lewat
        // CustomPermissionsSeeder (case enum-nya sendiri sudah dihapus dari kode).
    }
};
