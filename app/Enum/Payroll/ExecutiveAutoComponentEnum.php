<?php

namespace App\Enum\Payroll;

/**
 * Komponen yang TETAP dihitung otomatis buat 2 akun eksekutif non-karyawan
 * (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS), bukan cuma input manual lewat halaman Data Eksekutif
 * (instruksi user 2026-08-20: "misal Tunjangan Masa Kerja ngikut yang ada, ngga isi manual
 * lagi"). Enum TERPISAH dari PayrollComponentEnum (bukan static method di situ) - biar gampang
 * di-custom developer, tinggal tambah/hapus case di sini tanpa bongkar enum komponen utama.
 *
 * Cuma masukkan komponen yang PERHITUNGANNYA TIDAK BUTUH `employee_type_id`/AttendanceRecords
 * (yang eksekutif tidak punya, lihat PayrollComponentRecipient::isEligible()) - Tunjangan Masa
 * Kerja cuma butuh `joining_date` + tabel TenureAllowance, sama persis buat siapa pun. Tetap
 * ikut toggle aktif/nonaktif `company_payroll_components` seperti biasa, dan dipanggil LANGSUNG
 * (bukan lewat PayrollService::runComponent(), yang gerbang penerimanya otomatis blokir
 * employee_type_id null) - lihat PayrollService::generateForEmployee().
 *
 * Value tiap case WAJIB sama persis dengan PayrollComponentEnum yang bersangkutan -
 * toComponent()/toComponents() konversi ke situ buat dipakai PayrollCalculatorRegistry.
 */
enum ExecutiveAutoComponentEnum: string
{
    case TUNJANGAN_MASA_KERJA = 'tunjangan_masa_kerja';

    public function toComponent(): PayrollComponentEnum
    {
        return PayrollComponentEnum::from($this->value);
    }

    /** @return array<int, PayrollComponentEnum> */
    public static function toComponents(): array
    {
        return array_map(fn (self $case) => $case->toComponent(), self::cases());
    }
}
