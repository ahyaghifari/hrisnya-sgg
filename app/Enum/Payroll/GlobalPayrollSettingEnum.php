<?php

namespace App\Enum\Payroll;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary TERTUTUP untuk setting yang SELALU global (company_id NULL) — tidak pernah
 * di-override per company (beda dari setting yang di-assign ke PayrollComponentEnum, yang
 * selalu company-scoped, lihat PayrollComponentEnum::requiredSettings()). Diedit di 1 tempat
 * (panel atas halaman Komponen Gaji), berlaku sama ke semua company.
 */
enum GlobalPayrollSettingEnum: string
{
    case BPJS_KESEHATAN_RATE = 'bpjs_kesehatan_rate';
    case BPJS_KETENAGAKERJAAN_RATE = 'bpjs_ketenagakerjaan_rate';

    public function label(): string
    {
        return match ($this) {
            self::BPJS_KESEHATAN_RATE => 'Rate BPJS Kesehatan (%)',
            self::BPJS_KETENAGAKERJAAN_RATE => 'Rate BPJS Ketenagakerjaan (%)',
        };
    }

    public function type(): SettingTypeEnum
    {
        return SettingTypeEnum::DECIMAL;
    }
}
