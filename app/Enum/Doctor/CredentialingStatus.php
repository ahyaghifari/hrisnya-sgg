<?php

namespace App\Enum\Doctor;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Tahap case credentialing (docs/doctor_management_domain_spec.md, Fase 5). Status bebas dipilih admin; perubahan dicatat di audit log. */
enum CredentialingStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case PENGAJUAN = 'pengajuan';
    case VERIFIKASI = 'verifikasi';
    case KREDENSIAL = 'kredensial';
    case REKOMENDASI = 'rekomendasi';
    case SK_SPK = 'sk_spk';
    case SELESAI = 'selesai';
    case DITOLAK = 'ditolak';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENGAJUAN => 'Pengajuan',
            self::VERIFIKASI => 'Verifikasi',
            self::KREDENSIAL => 'Kredensial',
            self::REKOMENDASI => 'Rekomendasi',
            self::SK_SPK => 'SK SPK',
            self::SELESAI => 'Selesai',
            self::DITOLAK => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SELESAI => 'success',
            self::DITOLAK => 'danger',
            default => 'info',
        };
    }
}
