<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;

/**
 * Peta PayrollComponentEnum -> Calculator class (Layer 1 Registry -> Layer 2 Strategy).
 * Komponen yang belum punya entry di sini (LEMBUR versi Holding, IZIN_SEHARIAN,
 * IZIN_MENINGGALKAN_JAM_KERJA) sengaja BELUM dibangun — lihat PayrollComponentEnum::notYetAvailable()
 * dan planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md §2.2.
 */
class PayrollCalculatorRegistry
{
    private const MAP = [
        PayrollComponentEnum::GAJI_POKOK->value => GajiPokokCalculator::class,
        PayrollComponentEnum::TUNJANGAN_JABATAN->value => TunjanganJabatanCalculator::class,
        PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->value => TunjanganTransportasiCalculator::class,
        PayrollComponentEnum::TUNJANGAN_MASA_KERJA->value => TunjanganMasaKerjaCalculator::class,
        PayrollComponentEnum::KELEBIHAN_JAM_KERJA->value => KelebihanJamKerjaCalculator::class,
        PayrollComponentEnum::INSENTIF->value => IncentiveCalculator::class,
        PayrollComponentEnum::LEMBUR->value => LemburCalculator::class,
        PayrollComponentEnum::JASA_PELAYANAN->value => JasaPelayananCalculator::class,
        PayrollComponentEnum::BPJS_KESEHATAN->value => BpjsKesehatanCalculator::class,
        PayrollComponentEnum::BPJS_KETENAGAKERJAAN->value => BpjsKetenagakerjaanCalculator::class,
        PayrollComponentEnum::KETERLAMBATAN->value => KeterlambatanCalculator::class,
        PayrollComponentEnum::IZIN_TERLAMBAT_MASUK->value => IzinTerlambatMasukCalculator::class,
        PayrollComponentEnum::IZIN_PULANG_CEPAT->value => IzinPulangCepatCalculator::class,
        PayrollComponentEnum::CUTI->value => CutiCalculator::class,
        PayrollComponentEnum::KOPERASI->value => KoperasiCalculator::class,
        PayrollComponentEnum::BON->value => BonCalculator::class,
    ];

    /** @return class-string<PayrollComponentCalculator>|null null = belum ada Calculator */
    public static function resolve(PayrollComponentEnum $component): ?string
    {
        return self::MAP[$component->value] ?? null;
    }
}
