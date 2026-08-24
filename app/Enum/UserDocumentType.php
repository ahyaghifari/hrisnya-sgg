<?php

namespace App\Enum;

/**
 * EMPLOYMENT_CONTRACT (case lama) dihapus 2026-08-22 - surat kontrak kerja sekarang nempel
 * langsung di App\Models\User\UserContract::file_path (1 baris = 1 term kontrak), bukan lagi
 * arsip generik di sini. Data lama dihapus lewat migration
 * 2026_08_22_110500_drop_employment_contract_user_documents.php.
 */
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
            self::OTHER => 'Lainnya',
        };
    }
}
