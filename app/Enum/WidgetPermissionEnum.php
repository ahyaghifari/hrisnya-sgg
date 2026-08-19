<?php

namespace App\Enum;

/**
 * Permission KHUSUS widget Dashboard (kartu/ringkasan yang nempel di halaman Beranda portal,
 * bukan halaman/Resource berdiri sendiri) - dipisah dari CustomPermissionEnum (2026-08-19,
 * instruksi user) karena Dashboard diperkirakan bakal terus nambah widget baru ke depan, dan
 * mencampurnya ke satu enum besar bikin CustomPermissionEnum makin gemuk & tidak jelas mana
 * yang gerbang halaman vs gerbang widget. Pola/perilaku PERSIS sama seperti permission custom
 * biasa (flat, tanpa format "Verb:Subject", di-seed lewat WidgetPermissionSeeder bukan
 * CustomPermissionsSeeder) - cuma dikelompokkan di file/kelas terpisah.
 */
enum WidgetPermissionEnum: string
{
    case JADWAL_SHIFT_TIM = 'jadwal_shift_tim';

    public function label(): string
    {
        return match ($this) {
            self::JADWAL_SHIFT_TIM => 'Jadwal Shift Tim (Widget Dashboard)',
        };
    }
}
