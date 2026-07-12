<?php

namespace App\Enum;

/**
 * Permission "manual" (bukan format Shield Verb:Subject, tidak disentuh shield:generate) —
 * dipakai sebagai gerbang akses global ke suatu fitur (bukan permission CRUD per-Resource
 * biasa). Baris nilainya di database dibuat/dijaga lewat
 * database/seeders/CustomPermissionsSeeder.php — kalau nambah case baru di sini, tambahkan
 * juga ke $permissions di seeder itu lalu jalankan ulang:
 *   php artisan db:seed --class=CustomPermissionsSeeder
 */
enum CustomPermissionEnum: string
{
    case SISTEM_KEUANGAN = 'sistem_keuangan';

    public function label(): string
    {
        return match ($this) {
            self::SISTEM_KEUANGAN => 'Sistem Keuangan',
        };
    }
}
