<?php

namespace App\Enum;

/**
 * Jenis dokumen yang punya registry `documents` (Fase 1 TTE, docs/2026-09-28-tte-qr-internal-fase-1.md
 * §0) - PHP enum, bukan tabel DB (belum ada kebutuhan admin atur jenis dokumen sendiri lewat UI,
 * YAGNI). Tambah case baru begitu jenis dokumen baru siap implement Signable (mis. Surat Tugas).
 */
enum DocumentType: string
{
    case SP = 'sp';
    case BERITA_ACARA = 'berita_acara';
    case SURAT_TUGAS = 'surat_tugas';

    public function label(): string
    {
        return match ($this) {
            self::SP => 'Surat Peringatan',
            self::BERITA_ACARA => 'Berita Acara',
            self::SURAT_TUGAS => 'Surat Perintah Tugas',
        };
    }

    /** Prefix 2 huruf dipakai VerificationCodeGenerator - lihat App\Services\Document\VerificationCodeGenerator. */
    public function codePrefix(): string
    {
        return match ($this) {
            self::SP => 'SP',
            self::BERITA_ACARA => 'BA',
            self::SURAT_TUGAS => 'ST',
        };
    }
}
