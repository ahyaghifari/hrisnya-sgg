<?php

namespace App\Enum\Attendance;

/**
 * Jenis lembur - tetap & sama untuk semua perusahaan (tabel master m_overtime_types dihapus
 * 2026-09-20). Nilai enum = key yang dikirim form/API (`type_id`); yang disimpan di
 * attendance_req_overtime_details.overtime_type adalah label-nya (dibaca rekap, notifikasi, dst).
 */
enum OvertimeTypeEnum: string
{
    case BIASA = 'biasa';
    case KHUSUS = 'khusus';

    public function label(): string
    {
        return match ($this) {
            self::BIASA => 'Lembur Biasa',
            self::KHUSUS => 'Lembur Khusus/Proyek',
        };
    }

    /** Ikon Font Awesome (tombol pilihan jenis di form Lembur). */
    public function icon(): string
    {
        return match ($this) {
            self::BIASA => 'fa-clock',
            self::KHUSUS => 'fa-briefcase',
        };
    }

    /** @return array<string, string> key => label, buat <select>. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
