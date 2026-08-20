<?php

namespace App\Enum;

enum UserDocumentType: string
{
    case RESIGNATION = 'resignation';
    case RETIREMENT = 'retirement';
    case ASSIGNMENT = 'assignment';
    case APPOINTMENT = 'appointment';
    case TRANSFER = 'transfer';
    case WARNING_LETTER = 'warning_letter';
    case WORK_CERTIFICATE = 'work_certificate';
    case WORKING_EXPERIENCE_LETTER = 'working_experience_letter';
    case EMPLOYMENT_CONTRACT = 'employment_contract';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RESIGNATION => 'Pengunduran Diri',
            self::RETIREMENT => 'Surat Pensiun',
            self::ASSIGNMENT => 'Surat Tugas',
            self::APPOINTMENT => 'Surat Pengangkatan',
            self::TRANSFER => 'Surat Mutasi',
            self::WARNING_LETTER => 'Surat Peringatan',
            self::WORK_CERTIFICATE => 'Surat Keterangan Kerja',
            self::WORKING_EXPERIENCE_LETTER => 'Surat Pengalaman Kerja',
            self::EMPLOYMENT_CONTRACT => 'Surat Kontrak Kerja',
            self::OTHER => 'Lainnya',
        };
    }
}
