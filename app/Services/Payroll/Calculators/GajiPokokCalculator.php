<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * gaji_pokok = pangkat_salaries.base_salary * (1 + gaji_pokok_kenaikan_per_kelas% * steps)
 * steps = kelas.level user - pangkat.start_kelas_level, DIBATASI 0..(max_kelas_level -
 * start_kelas_level) — kelas di bawah start_kelas_level dianggap steps=0 (tidak dikurangi),
 * kelas di atas max_kelas_level dianggap sudah mentok di kelas maksimal pangkat itu (tidak terus
 * membesar tanpa batas kalau ada kesalahan assign kelas terlalu tinggi ke seorang karyawan).
 *
 * Persentase kenaikan per kelas itu SATU nilai per company (setting `gaji_pokok_kenaikan_per_kelas`,
 * halaman Komponen Gaji) — sama untuk semua pangkat di company itu, beda company boleh beda
 * persen. start_kelas_level/max_kelas_level tetap per-pangkat (halaman Mapping Komponen Gaji,
 * tab Gaji Pokok), karena rentang kelas memang beda tiap pangkat.
 *
 * ADDITIVE, bukan compound — dikonfirmasi user (analisis-payroll-4 §7.3): naik 2x kelas =
 * +16% dari base, BUKAN base*1.08^2.
 */
class GajiPokokCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $pangkat = $user->profile?->pangkat;
        $kelas = $user->profile?->kelas;

        if (! $pangkat || ! $kelas) {
            return;
        }

        $pangkatSalary = $pangkat->pangkatSalary;
        $rate = (float) Setting::resolve('gaji_pokok_kenaikan_per_kelas', $period->company_id, default: 0) / 100;

        // Skip aman kalau fondasi datanya belum lengkap — Gaji Pokok Rp0 yang ke-generate
        // diam-diam itu bug serius, jangan asumsikan 0 sebagai default "aman".
        if (! $pangkatSalary || $kelas->level === null || $pangkat->start_kelas_level === null) {
            return;
        }

        $steps = max(0, $kelas->level - $pangkat->start_kelas_level);

        if ($pangkat->max_kelas_level !== null) {
            $steps = min($steps, max(0, $pangkat->max_kelas_level - $pangkat->start_kelas_level));
        }

        $amount = round((float) $pangkatSalary->base_salary * (1 + $rate * $steps), 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::GAJI_POKOK->label(),
            'type' => PayrollComponentEnum::GAJI_POKOK->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => "Base Rp" . number_format((float) $pangkatSalary->base_salary, 0, ',', '.') . ", kelas +{$steps} langkah",
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);

        $context->addEarning($amount);
    }
}
