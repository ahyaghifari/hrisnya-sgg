<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Teknik 'per_komponen': tiap komponen di PRORATED_COMPONENTS (Tunjangan Jabatan/Transportasi/
 * Masa Kerja, Insentif, Jasa Pelayanan - SUDAH tergenerate Calculator masing-masing sebelum
 * Calculator ini jalan, lihat urutan di PayrollService::generateForUser()) dipotong (1-rasio)
 * dari nominal yang sudah jadi. Rasio (E-L)/E dikunci [0,1] - cuti penuh 1 periode (L>=E) ->
 * rasio 0 -> potong 100% komponen ini.
 *
 * Gaji Pokok, Lembur, Extra Shift, Jasa Unit, Fee Claim TIDAK disentuh - yang disebut terakhir
 * basisnya submission/kehadiran aktual (otomatis kecil sendiri kalau tidak masuk), bukan hak
 * tetap bulanan seperti Tunjangan/Insentif/Jasa Pelayanan (itu tetap penuh dari roster/rasio JE
 * x Omset, TIDAK otomatis mengecil walau karyawannya tidak masuk sama sekali) - dikonfirmasi
 * user 2026-09-05.
 */
class CutiPerKomponenCalculator extends AbstractCutiMelahirkanCalculator
{
    private const PRORATED_COMPONENTS = [
        PayrollComponentEnum::TUNJANGAN_JABATAN,
        PayrollComponentEnum::TUNJANGAN_TRANSPORTASI,
        PayrollComponentEnum::TUNJANGAN_MASA_KERJA,
        PayrollComponentEnum::INSENTIF,
        PayrollComponentEnum::JASA_PELAYANAN,
    ];

    protected function resolveAmount(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context, int $divisor, int $days): ?float
    {
        $ratio = max(0.0, min(1.0, ($divisor - $days) / $divisor));
        $reduction = 1 - $ratio;

        if ($reduction <= 0) {
            return 0.0;
        }

        $sourceTypes = array_map(fn (PayrollComponentEnum $c) => $c->value, self::PRORATED_COMPONENTS);

        // `amount` dienkripsi (PayrollEncrypted) - SUM SQL langsung di kolomnya bakal jumlahin
        // ciphertext (hasil ngaco), harus di-decrypt dulu baru dijumlah di PHP. Pola sama
        // IzinSeharianGajiPokokCalculator.
        $total = PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->whereIn('source_type', $sourceTypes)
            ->get(['amount'])
            ->sum('amount');

        return round((float) $total * $reduction, 2);
    }
}
