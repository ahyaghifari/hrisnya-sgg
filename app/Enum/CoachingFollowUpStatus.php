<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

/**
 * Evaluasi coach soal kondisi karyawan setelah coaching (planning/2026-09-23-sp-dan-merit-point.md
 * §1.1) — sekadar catatan kondisi, BUKAN syarat/gerbang buat aksi SP. 3 pilihan (NEEDS_SP
 * ditambah 2026-09-23, instruksi user): tombol pintasan menyesuaikan pilihan
 * (NEEDS_FOLLOW_UP -> Buat Coaching Lanjutan, NEEDS_SP -> Buat SP, prefill karyawan+coaching ini)
 * - tidak ada form SP yang dibangun di sini, cuma link ke CreateSpPage (Coaching & SP dipisah
 * total, lihat CoachingDetailPage).
 */
enum CoachingFollowUpStatus: string implements HasLabel
{
    case IMPROVED = 'improved';
    case NEEDS_FOLLOW_UP = 'needs_follow_up';
    case NEEDS_SP = 'needs_sp';

    public function getLabel(): string
    {
        return match ($this) {
            self::IMPROVED => 'Sudah Membaik',
            self::NEEDS_SP => 'Surat Peringatan',
            self::NEEDS_FOLLOW_UP => 'Coaching Lanjutan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::IMPROVED => 'success',
            self::NEEDS_FOLLOW_UP => 'warning',
            self::NEEDS_SP => 'danger',
        };
    }
}
