<?php

namespace App\Enum\Salary;

/**
 * Kategori komponen gaji — dipisah dari `name` (nama tampilan, bebas diubah admin) supaya
 * PayrollService punya kunci pencarian yang stabil. 6 kategori pertama = 1 kategori =
 * 1 baris katalog persis, dipakai lookup otomatis (getMasterComponent()). TUNJANGAN & BPJS
 * = kategori umum, bisa lebih dari 1 baris (mis. banyak jenis tunjangan), cuma dipakai buat
 * pengelompokan tampilan, tidak di-lookup by name di kode.
 *
 * Ganti dari SalaryComponentNameEnum (dihapus 2026-07-12) — enum lama sempat punya bug nyata:
 * case CUTI masih bernilai 'Leave' pad
 * ahal baris datanya sudah di-rename ke 'Cuti', jadi
 * lookup-nya diam-diam gagal. Kategori tidak kena masalah itu karena tidak terikat ke nama.
 */
enum SalaryComponentCategoryEnum: string
{
    case GAJI_POKOK = 'gaji_pokok';
    case TUNJANGAN = 'tunjangan';
    case CUTI = 'cuti';
    case IZIN = 'izin';
    case LEMBUR = 'lembur';
    case KETERLAMBATAN = 'keterlambatan';
    case JASA_PELAYANAN = 'jasa_pelayanan';
    case PAJAK_JASA_DOKTER = 'pajak_jasa_dokter';
    case BPJS = 'bpjs';

    /**
     * earning/deduction ditentukan sepenuhnya oleh kategori — bukan input terpisah,
     * supaya tidak ada 2 field yang bisa saling bertentangan (mis. kategori CUTI tapi
     * type-nya kepilih earning).
     */
    public function type(): SalaryComponentTypeEnum
    {
        return match ($this) {
            self::GAJI_POKOK, self::TUNJANGAN, self::JASA_PELAYANAN, self::LEMBUR => SalaryComponentTypeEnum::EARNING,
            self::CUTI, self::IZIN, self::KETERLAMBATAN, self::PAJAK_JASA_DOKTER, self::BPJS => SalaryComponentTypeEnum::DEDUCTION,
        };
    }

    /**
     * Nama warna badge Filament (dipakai di tabel Komponen Gaji & legend Pendapatan/Potongan).
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::GAJI_POKOK => 'primary',
            self::TUNJANGAN => 'info',
            self::JASA_PELAYANAN => 'success',
            self::BPJS => 'warning',
            self::CUTI, self::IZIN, self::LEMBUR => 'gray',
            self::KETERLAMBATAN, self::PAJAK_JASA_DOKTER => 'danger',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GAJI_POKOK => 'Gaji Pokok',
            self::CUTI => 'Cuti',
            self::IZIN => 'Izin',
            self::LEMBUR => 'Lembur',
            self::KETERLAMBATAN => 'Keterlambatan',
            self::JASA_PELAYANAN => 'Jasa Pelayanan',
            self::PAJAK_JASA_DOKTER => 'Pajak Jasa Dokter',
            
            self::TUNJANGAN => 'Tunjangan',
            self::BPJS => 'BPJS',
        };
    }

    /**
     * Kategori umum yang biasa ke-assign ke Jabatan (bisa lebih dari 1 baris katalog per
     * kategori) — dipakai buat nampilin daftar komponen per jabatan di halaman admin.
     */
    public static function jobPositionCategories(): array
    {
        return [self::TUNJANGAN, self::BPJS];
    }

    /**
     * Kategori yang tampil di section "Umum" (global, company_id selalu NULL).
     */
    public static function generalCategories(): array
    {
        return [self::CUTI, self::IZIN, self::KETERLAMBATAN, self::LEMBUR];
    }
}
