<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\Payroll\PayrollComponentEnum;

/**
 * Digabung dari IzinTerlambatPulangCepatCalculator (sebelumnya cuma proses 2 kategori) +
 * IZIN_MENINGGALKAN_JAM_KERJA (sub-jenis "tengah hari", sebelumnya sengaja TIDAK ada dampak
 * payroll) - sekarang ketiganya 1 komponen potongan yang sama, matching kelompok Izin
 * Meninggalkan Jam Kerja di form pengajuan (PermissionGroupEnum::MENINGGALKAN_JAM_KERJA).
 */
class IzinMeninggalkanJamKerjaCalculator extends AbstractIzinFlatCalculator
{
    protected function permissionTypeCategories(): array
    {
        return [
            PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value,
            PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value,
            PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA->value,
        ];
    }

    protected function componentValue(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->value;
    }

    protected function componentLabel(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->label();
    }

    protected function componentType(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->type()->value;
    }
}
