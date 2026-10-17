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
            ->groupBy(fn (Payroll $payroll) => $payroll->employee?->division?->name ?? $noDivisionLabel)
            ->sortBy(
                fn ($rows, $label) => ($label === $noDivisionLabel ? '0_' : '1_') . $label,
                SORT_STRING,
            );
    }

    /**
     * Urutan dalam 1 divisi: pangkat paling tinggi dulu (Pangkats::level), lalu kelas paling
     * tinggi (Kelas::level), lalu tanggal masuk paling lama, baru nama sebagai tie-breaker
     * terakhir - karyawan tanpa pangkat/kelas/tanggal masuk ditaruh paling belakang di
     * kriteria masing-masing (2026-08-26, instruksi user - sebelumnya bulan kerja).
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

            $kelasCompare = ($this->kelasLevel($b) ?? -INF) <=> ($this->kelasLevel($a) ?? -INF);
            if ($kelasCompare !== 0) {
                return $kelasCompare;
            }

            // Ascending (paling lama/senior dulu) - tanpa tanggal masuk ditaruh paling
            // belakang, jadi diperlakukan sebagai +INF di sisi kanan spaceship.
            $joiningCompare = ($this->joiningTimestamp($a) ?? INF) <=> ($this->joiningTimestamp($b) ?? INF);
            if ($joiningCompare !== 0) {
                return $joiningCompare;
            }

            return strcmp((string) $a->employee?->nama_lengkap, (string) $b->employee?->nama_lengkap);
        })->values();
    }

    private function pangkatLevel(Payroll $payroll): ?int
    {
        return $payroll->employee?->pangkat?->level;
    }

    private function kelasLevel(Payroll $payroll): ?int
    {
        return $payroll->employee?->kelas?->level;
    }

    private function joiningTimestamp(Payroll $payroll): ?int
    {
        $joiningDate = $payroll->employee?->joining_date;

        return $joiningDate ? Carbon::parse($joiningDate)->timestamp : null;
    }

    /**
     * Digenapkan ke bulan penuh (floor, bukan desimal), dihitung sampai AKHIR PERIODE GAJI
     * (bukan now()) - supaya hasilnya sama persis rumus Excel DATEDIF(Tanggal Masuk; Periode
     * Gaji; "m") dan konsisten walau file dibuka/dibuat kapan saja (2026-08-26, instruksi
     * user - sebelumnya pakai now(), salah kalau export dijalankan telat dari periodenya).
     */
    private function bulanKerja(Payroll $payroll): ?int
    {
        $joiningDate = $payroll->employee?->joining_date;
        $periodEnd = $payroll->payrollPeriod?->end_date;

        if (! $joiningDate || ! $periodEnd) {
            return null;
        }

        return (int) floor(Carbon::parse($joiningDate)->diffInMonths(Carbon::parse($periodEnd)));
    }
}
