<?php

namespace App\Enum\Attendance;

/**
 * Jenis Izin - tetap & sama untuk semua perusahaan (tabel master m_permission_types dihapus
 * 2026-09-20). Nilai enum = key yang dikirim form/API (`type_id`); yang disimpan di
 * attendance_req_permission_details adalah label-nya (`permission_type`) + `category`.
 *
 * Kelompok Meninggalkan Jam Kerja (3 jenis) & Khusus (8 jenis) dipilih user di level 2 form;
 * Tidak Masuk & Sakit flat (tanpa level 2) tapi tetap punya case di sini supaya API/`type_id`
 * seragam dan nama jenis yang tersimpan konsisten ("Izin Tidak Masuk", "Izin Sakit").
 */
enum PermissionTypeEnum: string
{
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

    public function label(): string
    {
        return match ($this) {
            self::TERLAMBAT => 'Izin Terlambat',
            self::PULANG_CEPAT => 'Izin Pulang Lebih Awal',
            self::MENINGGALKAN_JAM_KERJA => 'Izin Meninggalkan Jam Kerja',
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

    public function description(): ?string
    {
        return match ($this) {
            self::MENIKAH_KARYAWAN => '3 hari kerja, atau 5 hari kerja kalau lokasi pernikahan >150km dari tempat kerja - geser tanggal selesai manual kalau berlaku, tidak dihitung otomatis oleh sistem.',
            self::MENIKAH_ANAK, self::ISTRI_MELAHIRKAN, self::KHITAN_BAPTIS_ANAK => '2 hari kerja.',
            self::KELUARGA_INTI_MENINGGAL => 'Suami/istri, orang tua, atau anak sah karyawan meninggal - 3 hari kerja.',
            self::KELUARGA_SERUMAH_MENINGGAL => 'Anggota keluarga yang tinggal serumah (di luar suami/istri/ortu/anak) meninggal - 2 hari kerja.',
            self::MUSIBAH => 'Kebakaran, kebanjiran, atau musibah lain yang menimpa karyawan - lama izin atas pertimbangan pimpinan perusahaan, tidak ada jumlah hari baku. Isi tanggal sesuai kesepakatan dengan atasan/HC.',
            self::IBADAH_HAJI => 'Sesuai durasi ibadah haji ditambah 2 hari sebelum keberangkatan dan 2 hari setelah tiba kembali di tanah air. Isi tanggal mulai/selesai sesuai jadwal keberangkatan-kepulangan yang sebenarnya.',
            default => null,
        };
    }

    /**
     * Pilihan level 2 sebuah kelompok (Meninggalkan Jam Kerja / Khusus).
     *
     * @return array<int, self>
     */
    public static function forGroup(PermissionGroupEnum $group): array
    {
        $categories = $group->categories();

        return array_values(array_filter(self::cases(), fn (self $t) => in_array($t->category(), $categories, true)));
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
