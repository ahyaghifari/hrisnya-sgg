<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;

/**
 * Peta PayrollComponentEnum -> Calculator class (Layer 1 Registry -> Layer 2 Strategy).
 * Komponen yang belum punya entry di sini (LEMBUR versi Holding, TL/TM khusus) sengaja BELUM
 * dibangun — lihat planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md §2.2
 * dan planning/2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md.
 *
 * IZIN_SEHARIAN sengaja TIDAK dipanggil lewat Registry ini di orchestrator — dijalankan
 * terpisah sebagai langkah terakhir karena butuh $context->grossSalary yang sudah final (lihat
 * PayrollService::generatePayroll()), tapi tetap didaftarkan di sini biar `resolve()` konsisten
 * dipakai untuk cek "komponen ini punya Calculator atau belum" dari mana saja.
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
        PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->value => IzinMeninggalkanJamKerjaCalculator::class,
        PayrollComponentEnum::IZIN_SEHARIAN->value => IzinSeharianCalculator::class,
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
