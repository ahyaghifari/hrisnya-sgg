<?php

namespace App\Enum;

enum UserDocumentType: string
{
    case RESIGNATION = 'resignation';
    case RETIREMENT = 'retirement';
    case ASSIGNMENT = 'assignment';
    case WORK_CERTIFICATE = 'work_certificate';
    case EMPLOYMENT_CONTRACT = 'employment_contract';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RESIGNATION => 'Pengunduran Diri',
            self::RETIREMENT => 'Surat Pensiun',
            self::ASSIGNMENT => 'Surat Tugas',
            self::WORK_CERTIFICATE => 'Surat Keterangan Kerja',
            self::EMPLOYMENT_CONTRACT => 'Surat Kontrak Kerja',
            self::OTHER => 'Lainnya',
        };
    }
}
