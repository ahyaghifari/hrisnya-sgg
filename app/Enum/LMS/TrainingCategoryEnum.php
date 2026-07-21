<?php

namespace App\Enum\LMS;

enum TrainingCategoryEnum: string
{
    case UMUM = 'umum';
    case SERTIFIKASI_DOKTER_UMUM = 'sertifikasi_dokter_umum';
    case DOKTER_SPESIALIS = 'dokter_spesialis';
    case EKSTERNAL = 'eksternal';

    public function label(): string
    {
        return match ($this) {
            self::UMUM => 'Pelatihan Umum',
            self::SERTIFIKASI_DOKTER_UMUM => 'Sertifikasi / Dokter Umum',
            self::DOKTER_SPESIALIS => 'Dokter Spesialis',
            self::EKSTERNAL => 'Pelatihan Eksternal',
        };
    }

    /**
     * Honor Umum & Sertifikasi/Dokter Umum sudah fixed (tidak dihitung).
     * Dokter Spesialis & Eksternal dihitung lewat skor Variabel/Kategori/Range.
     */
    public function usesScoredHonor(): bool
    {
        return match ($this) {
            self::DOKTER_SPESIALIS, self::EKSTERNAL => true,
            default => false,
        };
    }

    /**
     * Konteks skor dipisah dua kolam (Dokter Spesialis vs Eksternal) supaya
     * kategori dari 2 konteks tidak tercampur di TrainerScoreVariables/Ranges.
     * Hanya kasus yang usesScoredHonor() punya konteks.
     */
    public static function scoredCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case->usesScoredHonor()));
    }

    /** Solid/soft/text dipakai buat tab & badge — beda warna per konteks biar tidak ketuker. */
    public function color(): array
    {
        return match ($this) {
            self::DOKTER_SPESIALIS => ['solid' => '#3730a3', 'soft' => 'rgba(55,48,163,0.10)', 'text' => '#3730a3'],
            self::EKSTERNAL => ['solid' => '#92400e', 'soft' => 'rgba(146,64,14,0.10)', 'text' => '#92400e'],
            self::UMUM => ['solid' => '#047C54', 'soft' => 'rgba(4,124,84,0.10)', 'text' => '#047C54'],
            self::SERTIFIKASI_DOKTER_UMUM => ['solid' => '#0f766e', 'soft' => 'rgba(15,118,110,0.10)', 'text' => '#0f766e'],
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DOKTER_SPESIALIS => 'fa-user-doctor',
            self::EKSTERNAL => 'fa-plane-departure',
            self::UMUM => 'fa-chalkboard-user',
            self::SERTIFIKASI_DOKTER_UMUM => 'fa-certificate',
        };
    }
}
