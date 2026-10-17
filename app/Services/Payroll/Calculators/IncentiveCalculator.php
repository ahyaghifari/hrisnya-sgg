<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Employee\EmployeeIncentive;
use App\Models\Payroll\LegacyPositionAllowance;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PositionAllowance;
use App\Models\Setting;
use App\Models\User;

/**
 * Metode ditentukan setting `insentif_metode` (company-scoped, lihat requiredSettings() di
 * PayrollComponentEnum::INSENTIF). "master" (Holding): roster ala Koperasi (`employee_koperasi`) —
 * semua baris `is_active=true` milik karyawan ikut DIJUMLAHKAN setiap Generate/Regenerate,
 * TIDAK difilter tanggal periode sama sekali. `incentive_date` cuma keterangan "diterima sejak
 * kapan", bukan syarat. Nonaktifkan lewat toggle di halaman Insentif kalau mau berhenti.
 * "auto" (RSU, kompensasi transisi Tunjangan Jabatan lama->baru): dihitung LIVE dari
 * legacy_position_allowances - position_allowances berdasarkan pangkat_id/kelas_id karyawan
 * SAAT INI, dikunci minimal 0 (lihat calculateFromLegacyGap()). Naik pangkat/kelas otomatis
 * mengecilkan/menghilangkan gap-nya karena dihitung ulang dari kombinasi baru tiap Generate,
 * bukan nominal yang dikunci permanen per orang.
 *
 * Metode "import" (dari payroll_manual_lines lewat upload Excel) SUDAH DIHAPUS - satu-satunya
 * jalur non-master (import) rawan baris hilang/dobel begitu unique constraint
 * payroll_manual_lines dilonggarkan (lihat migration 2026_08_01_160000_*), dan dashboard payroll
 * hardcode asumsi metode "master" sehingga company "import" tidak pernah terlihat datanya di
 * situ. Company yang sebelumnya pakai "import" harus pindah ke "master" (roster Insentif) atau
 * "auto".
 */
class IncentiveCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $metode = Setting::resolve('insentif_metode', $period->company_id, default: 'master');

        if ($metode === 'auto') {
            $this->calculateFromLegacyGap($user, $payroll, $context);

            return;
        }

        $incentives = EmployeeIncentive::query()
            ->where('employee_id', $payroll->employee_id)
            ->where('is_active', true)
            ->get();

        if ($incentives->isEmpty()) {
            return;
        }

        $totalAmount = (float) $incentives->sum('amount');

        if ($totalAmount <= 0) {
            return;
        }

        $names = $incentives->pluck('name')->filter()->unique()->implode(', ');

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::INSENTIF->label(),
            'type' => PayrollComponentEnum::INSENTIF->type()->value,
            'quantity' => 1,
            'amount' => $totalAmount,
            'note' => $names ?: null,
            'source_type' => PayrollComponentEnum::INSENTIF->value,
        ]);

        $context->addEarning($totalAmount);
    }

    private function calculateFromLegacyGap(User $user, Payroll $payroll, PayrollContext $context): void
    {
        $pangkatId = $user->employee?->pangkat_id;
        $kelasId = $user->employee?->kelas_id;

        if (! $pangkatId || ! $kelasId) {
            return;
        }

        $old = LegacyPositionAllowance::query()
            ->where('pangkat_id', $pangkatId)->where('kelas_id', $kelasId)
            ->value('amount');
        $new = PositionAllowance::query()
            ->where('pangkat_id', $pangkatId)->where('kelas_id', $kelasId)
            ->value('amount');

        $gap = max(0, (float) $old - (float) $new);

        if ($gap <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::INSENTIF->label(),
            'type' => PayrollComponentEnum::INSENTIF->type()->value,
            'quantity' => 1,
            'amount' => $gap,
            'note' => 'Kompensasi transisi Tunjangan Jabatan lama',
            'source_type' => PayrollComponentEnum::INSENTIF->value,
        ]);

        $context->addEarning($gap);
    }
}
