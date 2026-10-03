<?php

namespace App\Enum\Attendance;

use App\Enum\Attendance\Concerns\HasCompanyLabelOverride;

/**
 * Jenis Izin - tetap & sama untuk semua perusahaan (tabel master m_permission_types dihapus
 * 2026-09-20). Nilai enum = key yang dikirim form/API (`type_id`); yang disimpan di
 * attendance_req_permission_details adalah label-nya (`permission_type`) + `category`.
 *
 * Kelompok Meninggalkan Jam Kerja (3 jenis) & Khusus (9 jenis) dipilih user di level 2 form;
 * Tidak Masuk & Sakit flat (tanpa level 2) tapi tetap punya case di sini supaya API/`type_id`
 * seragam dan nama jenis yang tersimpan konsisten ("Izin Tidak Masuk", "Izin Sakit").
 *
 * KELUARGA_INTI_SAKIT company-restricted (2 company override, lihat HasCompanyLabelOverride) -
 * instruksi user 2026-09-30, disaring di forGroup().
 */
enum PermissionTypeEnum: string
{
    use HasCompanyLabelOverride;

    // Meninggalkan Jam Kerja
    case TERLAMBAT = 'terlambat';
    case PULANG_CEPAT = 'pulang_cepat';
    case MENINGGALKAN_JAM_KERJA = 'meninggalkan_jam_kerja';

    // Flat
    case TIDAK_MASUK = 'tidak_masuk';
    case SAKIT = 'sakit';

    // Khusus
    case MENIKAH_KARYAWAN = 'menikah_karyawan';
    case MENIKAH_ANAK = 'menikah_anak';
    case ISTRI_MELAHIRKAN = 'istri_melahirkan';
    case KELUARGA_INTI_MENINGGAL = 'keluarga_inti_meninggal';
    case KELUARGA_SERUMAH_MENINGGAL = 'keluarga_serumah_meninggal';
    case KHITAN_BAPTIS_ANAK = 'khitan_baptis_anak';
    case MUSIBAH = 'musibah';
    case IBADAH_HAJI = 'ibadah_haji';
    case KELUARGA_INTI_SAKIT = 'keluarga_inti_sakit';
    case SURAT_TUGAS = 'surat_tugas';

    /** $companyId null = label default (semua company lain). 2 company override (lihat
     * HasCompanyLabelOverride) pakai penamaan sendiri buat Tidak Masuk/Sakit - instruksi user
     * 2026-09-30. */
    public function label(?string $companyId = null): string
    {
        if (self::usesLabelOverride($companyId)) {
            $override = match ($this) {
                self::TIDAK_MASUK => 'Izin Tidak Masuk / Sakit tanpa surat',
                self::SAKIT => 'Izin Sakit Bersurat',
                default => null,
            };

            if ($override !== null) {
                return $override;
            }
        }

        return match ($this) {
            self::TERLAMBAT => 'Izin Terlambat',
            self::PULANG_CEPAT => 'Izin Pulang Lebih Awal',
            self::MENINGGALKAN_JAM_KERJA => 'Izin Ditengah Jam Kerja',
            self::TIDAK_MASUK => 'Izin Tidak Masuk',
            self::SAKIT => 'Izin Sakit',
            self::MENIKAH_KARYAWAN => 'Izin Menikah (Karyawan)',
            self::MENIKAH_ANAK => 'Izin Menikah (Anak Sah Karyawan)',
            self::ISTRI_MELAHIRKAN => 'Izin Istri Melahirkan/Keguguran',
            self::KELUARGA_INTI_MENINGGAL => 'Izin Keluarga Inti Meninggal',
            self::KELUARGA_SERUMAH_MENINGGAL => 'Izin Keluarga Serumah Meninggal',
            self::KHITAN_BAPTIS_ANAK => 'Izin Mengkhitankan/Membaptiskan Anak',
            self::MUSIBAH => 'Izin Musibah',
            self::IBADAH_HAJI => 'Izin Ibadah Haji',
            self::KELUARGA_INTI_SAKIT => 'Izin Keluarga Inti Sakit',
            self::SURAT_TUGAS => 'Izin Surat Tugas',
        };
    }

    public function category(): PermissionTypeCategoryEnum
    {
        return match ($this) {
            self::TERLAMBAT => PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK,
            self::PULANG_CEPAT => PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT,
            self::MENINGGALKAN_JAM_KERJA => PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA,
            self::TIDAK_MASUK => PermissionTypeCategoryEnum::IZIN_SEHARIAN,
            self::SAKIT => PermissionTypeCategoryEnum::IZIN_SAKIT,
            default => PermissionTypeCategoryEnum::IZIN_KHUSUS,
        };
    }

    /** Jumlah hari baku Izin Khusus (prefill tanggal selesai) - null = tidak ada angka baku. */
    public function defaultDays(): ?int
    {
        return match ($this) {
            self::MENIKAH_KARYAWAN, self::KELUARGA_INTI_MENINGGAL => 3,
            self::MENIKAH_ANAK, self::ISTRI_MELAHIRKAN, self::KELUARGA_SERUMAH_MENINGGAL, self::KHITAN_BAPTIS_ANAK => 2,
            default => null,
        };
    }

    /** Batas hari maksimal tanggal selesai Izin Khusus - null = tidak dibatasi. Menikah karyawan 5 hari (lokasi >150km), selebihnya = defaultDays(). */
    public function maxDays(): ?int
    {
        return $this === self::MENIKAH_KARYAWAN ? 5 : $this->defaultDays();
    }

    public function description(): ?string
    {
        return match ($this) {
            self::MENIKAH_KARYAWAN => '3 hari kerja, atau 5 hari kerja kalau lokasi pernikahan >150km dari tempat kerja - geser tanggal selesai manual kalau berlaku, tidak dihitung otomatis oleh sistem.',
            self::MENIKAH_ANAK, self::ISTRI_MELAHIRKAN, self::KHITAN_BAPTIS_ANAK => '2 hari kerja.',
            self::KELUARGA_INTI_MENINGGAL => 'Suami/istri, orang tua, atau anak sah karyawan meninggal - 3 hari kerja.',
            self::KELUARGA_SERUMAH_MENINGGAL => 'Anggota keluarga yang tinggal serumah (di luar suami/istri/ortu/anak) meninggal - 2 hari kerja.',
            self::MUSIBAH => 'Kebakaran, kebanjiran, atau musibah lain yang menimpa karyawan - lama izin atas pertimbangan pimpinan perusahaan, tidak ada jumlah hari baku. Isi tanggal sesuai kesepakatan dengan atasan/HC.',
            self::IBADAH_HAJI => 'Sesuai durasi ibadah haji ditambah 2 hari sebelum keberangkatan dan 2 hari setelah tiba kembali di tanah air. Isi tanggal mulai/selesai sesuai jadwal keberangkatan-kepulangan yang sebenarnya.',
            self::KELUARGA_INTI_SAKIT => 'Suami/istri, orang tua, atau anak sah karyawan sakit - tidak ada jumlah hari baku, lama izin atas pertimbangan atasan/HC. Lampirkan surat rawat/keterangan dokter.',
            self::SURAT_TUGAS => 'Tugas/dinas di luar kantor berdasarkan surat tugas - isi tanggal sesuai surat tugas dan lampirkan scan/foto surat tugas.',
            default => null,
        };
    }

    /** Jenis yang cuma boleh dipilih 2 company override (lihat HasCompanyLabelOverride) -
     * instruksi user 2026-09-30. */
    public function isCompanyRestricted(): bool
    {
        return in_array($this, [self::KELUARGA_INTI_SAKIT, self::SURAT_TUGAS], true);
    }

    /**
     * Pilihan level 2 sebuah kelompok (Meninggalkan Jam Kerja / Khusus). $companyId dipakai
     * buat nyaring jenis yang company-restricted (KELUARGA_INTI_SAKIT).
     *
     * @return array<int, self>
     */
    public static function forGroup(PermissionGroupEnum $group, ?string $companyId = null): array
    {
        $categories = $group->categories();

        return array_values(array_filter(
            self::cases(),
            fn (self $t) => in_array($t->category(), $categories, true)
                && (! $t->isCompanyRestricted() || self::usesLabelOverride($companyId))
        ));
    }

    /** Jenis dari label yang tersimpan di detail pengajuan (`permission_type`), null kalau data lama/custom. */
    public static function fromLabel(?string $label): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->label() === $label) {
                return $type;
            }
        }

        return null;
    }

    /** Jenis pertama sebuah kategori - dipakai kelompok flat (Tidak Masuk/Sakit) yang cuma kirim category. */
    public static function firstForCategory(?string $category): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->category()->value === $category) {
                return $type;
            }
        }

        return null;
    }

    /** @return array<string, string> key => label (seluruh jenis, buat API). */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
