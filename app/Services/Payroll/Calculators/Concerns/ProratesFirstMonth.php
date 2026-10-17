<?php

namespace App\Services\Payroll\Calculators\Concerns;

use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Employee\Employee;
use Carbon\Carbon;

/**
 * Prorate bulan pertama untuk karyawan yang Tanggal Bergabungnya jatuh di tengah periode berjalan
 * — dipakai bareng oleh Gaji Pokok, Tunjangan Jabatan, Tunjangan Transportasi, Tunjangan Masa
 * Kerja (dikonfirmasi user 2026-08-05, awalnya cuma Gaji Pokok). Rasio & pembagi SATU sumber
 * di sini supaya keempatnya konsisten, bukan hitung ulang beda-beda di tiap Calculator.
 *
 * PEMBAGI di-resolve lewat ResolvesProrateDivisor (setting `prorate_metode`, sama sumbernya
 * dengan IzinSeharianKeseluruhanCalculator/IzinSeharianGajiPokokCalculator - dikonfirmasi user
 * 2026-08-05, awalnya cuma dipakai di sini), referensi bulannya BULAN Tanggal Bergabung
 * (bukan bulan periode - relevan kalau periode custom cutoff-nya lintas bulan).
 *
 * Pembilang: hari Senin-Sabtu (exclude Minggu saja, tanpa lihat jadwal/roster/hari libur per
 * karyawan - sengaja sederhana) dari Tanggal Bergabung s.d. akhir periode.
 *
 * Juga menyimpan resolveContractTransitionSignDate()/contractTransitionRatio() (instruksi user
 * 2026-09-15) - prorate KEDUA yang beda pivot (tanggal TTD kontrak, bukan Tanggal Bergabung),
 * dipakai bareng di sini karena butuh countProrateWorkDays/prorateDivisor yang sama.
 */
trait ProratesFirstMonth
{
    use ResolvesProrateDivisor;

    /**
     * @return array{ratio: float, note: string}|null null = tidak perlu diprorate (karyawan
     * lama, atau Tanggal Bergabung belum diisi / di luar periode ini).
     */
    protected function firstMonthProrate(Employee $employee, PayrollPeriod $period): ?array
    {
        // Tanggal Bergabung - dibaca dari employees.joining_date (di-cast Carbon).
        $joiningDate = $employee->joining_date ? Carbon::parse($employee->joining_date) : null;

        // Syarat prorate: (1) Tanggal Bergabung ADA, (2) jatuh SETELAH awal periode (baru masuk
        // di tengah bulan, bukan sudah dari awal), (3) masih di dalam rentang periode ini.
        if (! $joiningDate || ! $joiningDate->gt($period->start_date) || ! $joiningDate->lte($period->end_date)) {
            return null;
        }

        $divisor = $this->prorateDivisor($period, $joiningDate);

        if ($divisor <= 0) {
            return null;
        }

        $workedDays = $this->countProrateWorkDays($joiningDate, $period->end_date);
        // Rasio dikunci maksimal 1 (100%) - kalau workedDays kebetulan melebihi pembaginya,
        // tidak boleh dibayar lebih dari 1 bulan penuh.
        $ratio = min(1, $workedDays / $divisor);

        return [
            'ratio' => $ratio,
            'note' => "prorate bulan pertama {$workedDays}/{$divisor} {$this->prorateDivisorLabel($period)} sejak {$joiningDate->format('d-m-Y')}",
        ];
    }

    /**
     * Tanggal TTD kontrak PERTAMA (Orientasi->Kontrak, instruksi user 2026-09-15) kalau JATUH di
     * periode ini DAN setting `kontrak_prorate_metode` company ini = 'tiered' DAN tanggalnya > 5
     * (1-5 tetap dianggap penuh, tidak perlu prorate apapun - null juga di sini, caller pakai
     * jalur normal). "PERTAMA" (oldest by start_date) penting - perpanjangan kontrak
     * SELALU butuh kontrak lama sudah ada (lihat ContractExtensionService::followUpDuration,
     * lempar exception kalau `currentContract` kosong), jadi baris kontrak (employee_contracts) PALING AWAL
     * milik user pasti dari OrientationService::followUpContract, tidak pernah dari
     * perpanjangan - aman dipakai sebagai penanda "ini benar TTD pertama", bukan perpanjangan.
     */
    protected function resolveContractTransitionSignDate(Employee $employee, PayrollPeriod $period): ?Carbon
    {
        if (Setting::resolve('kontrak_prorate_metode', $period->company_id, default: 'penuh') !== 'tiered') {
            return null;
        }

        $firstContract = $employee->contracts()->oldest('start_date')->first();

        if (! $firstContract || ! $firstContract->start_date) {
            return null;
        }

        $signDate = Carbon::parse($firstContract->start_date);

        if (! $signDate->between($period->start_date, $period->end_date) || $signDate->day <= 5) {
            return null;
        }

        return $signDate;
    }

    /**
     * Prorate "Rp0 sebelum TTD, penuh sesudah TTD" - dipakai Tunjangan Jabatan/Transportasi/Masa
     * Kerja (instruksi user 2026-09-15: Orientasi TIDAK dapat tunjangan2 ini sama sekali, beda
     * dari Gaji Pokok yang tetap dapat base_salary polos saat Orientasi - blend khusus base+rate
     * di GajiPokokCalculator::contractTransitionAmount(), tidak bisa dipakai bareng di sini
     * karena "sebelum TTD"-nya bukan Rp0 buat Gaji Pokok).
     *
     * @return array{ratio: float, note: string}|null null = tidak ada transisi kontrak yang
     * relevan bulan ini, pakai jalur normal (firstMonthProrate kalau ada, atau penuh).
     */
    protected function contractTransitionRatio(Employee $employee, PayrollPeriod $period): ?array
    {
        $signDate = $this->resolveContractTransitionSignDate($employee, $period);

        if (! $signDate) {
            return null;
        }

        $divisor = $this->prorateDivisor($period, $signDate);

        if ($divisor <= 0) {
            return null;
        }

        // >20 = bulan ini masih Orientasi (Rp0 buat tunjangan ini) - caller (amount<=0) otomatis
        // skip generate PayrollItem, sama seperti kalau matriksnya memang tidak ada baris.
        if ($signDate->day > 20) {
            return ['ratio' => 0.0, 'note' => "TTD kontrak {$signDate->format('d-m-Y')} (>tgl 20): Rp0 bulan ini, penuh mulai bulan depan"];
        }

        $daysAfter = $this->countProrateWorkDays($signDate, $period->end_date);
        $ratio = min(1, $daysAfter / $divisor);

        return ['ratio' => $ratio, 'note' => "TTD kontrak {$signDate->format('d-m-Y')} (tgl 6-20): prorate {$daysAfter}/{$divisor} {$this->prorateDivisorLabel($period)} sejak TTD"];
    }

    /** Hari Senin-Sabtu dari $from s.d. $to (inklusif) - Minggu di-exclude. */
    private function countProrateWorkDays(Carbon $from, Carbon $to): int
    {
        $count = 0;

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if (! $date->isSunday()) {
                $count++;
            }
        }

        return $count;
    }
}
