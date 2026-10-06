<?php

namespace App\Enum\Doctor;

use Filament\Support\Contracts\HasLabel;

/** Jenis dokumen umum dokter (docs/doctor_management_domain_spec.md, Fase 2). SIP/STR/STR-KT punya tabel sendiri (Fase 3). */
enum DoctorDocumentType: string implements HasLabel
{
    case LAMARAN = 'LAMARAN';
    case CV = 'CV';
    case IJAZAH_DOKTER = 'IJAZAH_DOKTER';
    case IJAZAH_SPESIALIS = 'IJAZAH_SPESIALIS';
    case IJAZAH_SUBSPESIALIS = 'IJAZAH_SUBSPESIALIS';
    case TRANSKRIP = 'TRANSKRIP';
    case SERTIFIKAT_KOMPETENSI = 'SERTIFIKAT_KOMPETENSI';
    case SERTIFIKAT_PELATIHAN = 'SERTIFIKAT_PELATIHAN';
    case BUKU_REKENING = 'BUKU_REKENING';
    case KTP = 'KTP';
    case NPWP = 'NPWP';
    case CREDENTIALING = 'CREDENTIALING';

    public function getLabel(): string
    {
        return match ($this) {
            self::LAMARAN => 'Surat Lamaran',
            self::CV => 'CV',
            self::IJAZAH_DOKTER => 'Ijazah Dokter',
            self::IJAZAH_SPESIALIS => 'Ijazah Spesialis',
            self::IJAZAH_SUBSPESIALIS => 'Ijazah Subspesialis',
            self::TRANSKRIP => 'Transkrip',
            self::SERTIFIKAT_KOMPETENSI => 'Sertifikat Kompetensi',
            self::SERTIFIKAT_PELATIHAN => 'Sertifikat Pelatihan',
            self::BUKU_REKENING => 'Buku Rekening',
            self::KTP => 'KTP',
            self::NPWP => 'NPWP',
            self::CREDENTIALING => 'PDF Credentialing',
        };
    }
}
