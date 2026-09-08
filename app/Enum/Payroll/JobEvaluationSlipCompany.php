<?php

namespace App\Enum\Payroll;

/**
 * Perusahaan yang menampilkan baris "Nilai Job Evaluation (JE)" di slip gaji. Selain
 * perusahaan di sini, baris JE disembunyikan meski jabatannya punya job_evaluation_score.
 * Tambah perusahaan = tambah satu case (value = kode perusahaan / m_companies.code).
 */
enum JobEvaluationSlipCompany: string
{
    case RSU_SYIFA_MEDIKA_BANJARBARU = '0001';
    case RSU_SYIFA_MEDIKA_BARABAI = '0002';

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public static function shows(?string $companyCode): bool
    {
        return $companyCode !== null && in_array($companyCode, self::codes(), true);
    }
}
