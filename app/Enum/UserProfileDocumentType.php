<?php

namespace App\Enum;

/**
 * Lampiran identitas di Data Diri (UserProfileDocument::label) - dulu bebas ketik sendiri,
 * sekarang tipe tetap (instruksi user 2026-10-05). STR/SIP/RKK cuma relevan buat nakes
 * (JobPositions::is_nakes) - form Data Diri sembunyikan opsi ini utk jabatan non-nakes, DAN
 * addProfileDocument() tolak submit-nya di server (jangan cuma sembunyi di UI).
 */
enum UserProfileDocumentType: string
{
    case KTP = 'KTP';
    case KK = 'KK';
    case STR = 'STR';
    case SIP = 'SIP';
    case RKK = 'RKK';

    public function label(): string
    {
        return match ($this) {
            self::KTP => 'KTP',
            self::KK => 'Kartu Keluarga (KK)',
            self::STR => 'STR (Surat Tanda Registrasi)',
            self::SIP => 'SIP (Surat Izin Praktik)',
            self::RKK => 'RKK (Rincian Kewenangan Klinis)',
        };
    }

    public function nakesOnly(): bool
    {
        return match ($this) {
            self::STR, self::SIP, self::RKK => true,
            default => false,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::KTP => 'fa-solid fa-id-card',
            self::KK => 'fa-solid fa-people-roof',
            self::STR => 'fa-solid fa-stethoscope',
            self::SIP => 'fa-solid fa-briefcase-medical',
            self::RKK => 'fa-solid fa-clipboard-list',
        };
    }

    /** Varian warna .pwa-tile-icon (app.css) - KTP/KK tetap netral, STR/SIP/RKK dibedakan
     * (request user) karena cuma relevan buat nakes. */
    public function tileColor(): string
    {
        return match ($this) {
            self::STR => 'teal',
            self::SIP => 'gold',
            self::RKK => 'rust',
            default => 'neutral',
        };
    }

    /** Nama properti Livewire yang nampung file upload slot ini - lihat EmployeeStatistics. */
    public function uploadProperty(): string
    {
        return match ($this) {
            self::KTP => 'ktpFile',
            self::KK => 'kkFile',
            self::STR => 'strFile',
            self::SIP => 'sipFile',
            self::RKK => 'rkkFile',
        };
    }

    /** @return array<int, self> */
    public static function options(bool $isNakes): array
    {
        return array_values(array_filter(
            self::cases(),
            // ponytail: RKK disembunyikan dari form Data Diri (request user), case tetap ada buat data lama.
            fn (self $case) => $case !== self::RKK && ($isNakes || ! $case->nakesOnly())
        ));
    }
}
