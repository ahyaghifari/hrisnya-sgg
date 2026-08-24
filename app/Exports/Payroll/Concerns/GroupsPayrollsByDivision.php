<?php

namespace App\Exports\Payroll\Concerns;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Grouping "per DIVISI" (org level 2) + fallback buat karyawan tanpa divisi (org_structure
 * kosong) - "Presiden Direktur" kalau company HOLDING (kode '0000', 2 akun eksekutif non-karyawan
 * yang tetap punya payroll - lihat User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS), "Manajemen" kalau
 * company biasa. Grup tanpa divisi SELALU ditaruh paling atas, divisi asli diurutkan alfabetis
 * di bawahnya. Diekstrak dari PayrollSlipRekapExport (2026-08-22) supaya dipakai bareng
 * PajakGajiPokokExport tanpa duplikasi (sebelumnya cuma dipakai 1 export).
 */
trait GroupsPayrollsByDivision
{
    private const NO_DIVISION_LABEL_HOLDING = 'Presiden Direktur';

    private const NO_DIVISION_LABEL_REGULAR = 'Manajemen';

    private const HOLDING_COMPANY_CODE = '0000';

    private function isHoldingCompany(string $companyId): bool
    {
        return Companies::where('id', $companyId)->value('code') === self::HOLDING_COMPANY_CODE;
    }

    /** @param Collection<int, Payroll> $payrolls @return Collection<string, Collection<int, Payroll>> keyed by nama divisi/label fallback, sudah terurut. */
    private function groupedByDivision(Collection $payrolls, string $companyId): Collection
    {
        $noDivisionLabel = $this->isHoldingCompany($companyId) ? self::NO_DIVISION_LABEL_HOLDING : self::NO_DIVISION_LABEL_REGULAR;

        return $payrolls
            ->groupBy(fn (Payroll $payroll) => $payroll->user?->profile?->division?->name ?? $noDivisionLabel)
            ->sortBy(
                fn ($rows, $label) => ($label === $noDivisionLabel ? '0_' : '1_') . $label,
                SORT_STRING,
            );
    }

    /**
     * Urutan dalam 1 divisi: pangkat paling tinggi dulu (Pangkats::level), lalu bulan kerja
     * paling lama, baru nama - karyawan tanpa pangkat/tanggal masuk ditaruh paling belakang.
     *
     * @param Collection<int, Payroll> $payrolls
     */
    private function sortWithinDivision(Collection $payrolls): Collection
    {
        return $payrolls->sort(function (Payroll $a, Payroll $b) {
            $levelCompare = ($this->pangkatLevel($b) ?? -INF) <=> ($this->pangkatLevel($a) ?? -INF);
            if ($levelCompare !== 0) {
                return $levelCompare;
            }

            $tenureCompare = ($this->bulanKerja($b) ?? -INF) <=> ($this->bulanKerja($a) ?? -INF);
            if ($tenureCompare !== 0) {
                return $tenureCompare;
            }

            return strcmp((string) $a->user?->name, (string) $b->user?->name);
        })->values();
    }

    private function pangkatLevel(Payroll $payroll): ?int
    {
        return $payroll->user?->profile?->pangkat?->level;
    }

    /** Digenapkan ke bulan penuh (floor, bukan desimal). */
    private function bulanKerja(Payroll $payroll): ?int
    {
        $joiningDate = $payroll->user?->profile?->joining_date;

        return $joiningDate ? (int) floor(Carbon::parse($joiningDate)->diffInMonths(now())) : null;
    }
}
