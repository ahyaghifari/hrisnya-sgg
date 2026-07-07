<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ID company holding (Syifa Global Group) — satu-satunya company yang
     * role "Super Admin"-nya dimaksudkan bypass semua company (superadmin global).
     */
    private const HOLDING_COMPANY_ID = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';

    /**
     * RoleSeeder membuat role "Super Admin" di SETIAP company (niatnya jadi admin
     * lokal per company, full akses tapi cuma company sendiri). Karena Spatie
     * hasRole() cuma cek name+guard_name (kolom company_id di tabel roles bukan
     * bagian dari mekanisme Spatie, dan fitur teams tidak diaktifkan), semua role
     * "Super Admin" di company mana pun ikut ke-treat sebagai superadmin global.
     * Migration ini merename role tsb di company selain holding jadi "Admin",
     * supaya "Super Admin" cuma berlaku untuk company holding.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'Super Admin')
            ->where('company_id', '!=', self::HOLDING_COMPANY_ID)
            ->update(['name' => 'Admin']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'Admin')
            ->where('company_id', '!=', self::HOLDING_COMPANY_ID)
            ->update(['name' => 'Super Admin']);
    }
};
