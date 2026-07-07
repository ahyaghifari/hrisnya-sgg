<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Perbaikan data satu kali: role "Super Admin" seharusnya cuma ada di company holding
 * (Syifa Global Group). Company lain semestinya cuma sampai "Admin" — tapi RoleSeeder lama
 * bikin "Super Admin" rata di SEMUA company. Seeder ini rename baris yang salah itu jadi "Admin".
 *
 * Sengaja RENAME (bukan hapus + buat baru), supaya ID role tidak berubah — user yang sudah
 * ke-assign ke role ini (ada 3 user aktif di RSU Syifa Medika Banjarbaru) otomatis ikut
 * berubah sebutannya jadi "Admin" TANPA kehilangan assignment/akses sama sekali.
 *
 * Aman dijalankan berulang (kalau sudah ke-rename, query where name='Super Admin' AND
 * company_id != holding tidak akan nemu apa-apa lagi).
 */
class RenameCompanySuperAdminToAdminSeeder extends Seeder
{
    private const HOLDING_COMPANY_ID = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';

    public function run(): void
    {
        Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->where('company_id', '!=', self::HOLDING_COMPANY_ID)
            ->update(['name' => 'Admin']);
    }
}
