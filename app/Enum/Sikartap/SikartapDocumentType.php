<?php

namespace App\Enum\Sikartap;

use Filament\Support\Contracts\HasLabel;

enum SikartapDocumentType: string implements HasLabel
{
    case SURAT_PERMOHONAN = 'surat_permohonan';
    case FORMULIR_REKOMENDASI_ATASAN = 'formulir_rekomendasi_atasan';
    case FORMULIR_REKOMENDASI_PEJABAT = 'formulir_rekomendasi_pejabat';
    case PENILAIAN_KINERJA = 'penilaian_kinerja';
    case IJAZAH_TERAKHIR = 'ijazah_terakhir';
    case STR = 'str_file';
    case SIP = 'sip_file';
    case RKK_SPK = 'rkk_spk';
    case SERTIFIKAT_BTCLS = 'sertifikat_btcls';
    case KTP_KK = 'ktp_kk';
    case RIWAYAT_PELATIHAN = 'riwayat_pelatihan';

    public function getLabel(): string
    {
        return match ($this) {
            self::SURAT_PERMOHONAN => 'Surat Permohonan',
            self::FORMULIR_REKOMENDASI_ATASAN => 'Formulir Rekomendasi Atasan',
            self::FORMULIR_REKOMENDASI_PEJABAT => 'Formulir Rekomendasi Pejabat',
            self::PENILAIAN_KINERJA => 'Penilaian Kinerja',
            self::IJAZAH_TERAKHIR => 'Ijazah Terakhir',
            self::STR => 'STR',
            self::SIP => 'SIP',
            self::RKK_SPK => 'RKK dan SPK',
            self::SERTIFIKAT_BTCLS => 'Sertifikat BTCLS',
            self::KTP_KK => 'KTP dan KK',
            self::RIWAYAT_PELATIHAN => 'Riwayat Pelatihan',
        };
    }

    /** Dokumen wajib buat gate "berkas lengkap" (§ verifikasi_berkas.php legacy). */
    public function isRequired(): bool
    {
        return in_array($this, [
            self::SURAT_PERMOHONAN,
            self::FORMULIR_REKOMENDASI_ATASAN,
            self::FORMULIR_REKOMENDASI_PEJABAT,
            self::PENILAIAN_KINERJA,
            self::IJAZAH_TERAKHIR,
            self::KTP_KK,
        ], true);
    }

    /** @return array<string, string> value => label, buat dropdown Filament/Livewire. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
