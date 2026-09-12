<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ShiftType: string implements HasLabel
{
    case PAGI = 'pagi';
    case SIANG = 'siang';
    case MALAM = 'malam';

    /** Extra shift khusus buat karyawan yang LIBUR (standby/dipanggil sewaktu-waktu) - bukan
     * shift roster normal. Dihitung fleksibel (bukan jendela jam kaku seperti Pagi/Siang/Malam),
     * TAPI mekanisme kalkulasinya BELUM diubah (masih ikut ShiftScheduleCalculator apa adanya) -
     * lihat planning kalau ada, atau chat 2026-09-12. Pembatasan "cuma boleh kalau lagi libur"
     * sudah otomatis kepakai lewat ExtraShiftRule from_shift_type='libur' (sentinel di
     * ResolvesExtraShiftOptions), tidak perlu kode tambahan. */
    case ONCALL = 'oncall';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PAGI => 'Pagi',
            self::SIANG => 'Siang/Sore',
            self::MALAM => 'Malam',
            self::ONCALL => 'Oncall/Middle',
        };
    }
}
