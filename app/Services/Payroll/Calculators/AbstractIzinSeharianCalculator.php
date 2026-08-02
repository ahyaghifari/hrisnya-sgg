<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodResolver;

/**
 * Basis bersama kedua metode Izin Seharian (setting `izin_seharian_metode`, dipilih di halaman
 * Mapping Komponen Gaji) - hitung jumlah kejadian izin, lalu kalikan rate harian yang beda
 * rumusnya per metode (lihat subclass). Dipecah jadi Calculator terpisah (bukan 1 class dengan
 * percabangan private method) supaya masing-masing metode punya rumusnya sendiri yang jelas dan
 * gampang ditest terpisah - IzinSeharianCalculator sendiri cuma dispatcher tipis yang pilih
 * salah satu dari dua ini berdasar setting.
 */
abstract class AbstractIzinSeharianCalculator implements PayrollComponentCalculator
{
    /** Rate harian (Rp per hari izin) - 0 berarti tidak ada dasar hitung, tidak digenerate. */
    abstract protected function rateHarian(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): float;

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        // Window pengecekan tanggal izin BUKAN literal start_date/end_date PayrollPeriod (itu
        // diketik bebas admin pas bikin periode gaji) - dipakai window "periode Izin" dari
        // Pengaturan Absensi (AttendanceSettingEnum::PERIOD_START_DAY/END_DAY, default 21-20),
        // sama seperti yang dipakai buat kuota Izin (2026-08-02).
        [$izinPeriodStart, $izinPeriodEnd] = AttendancePeriodResolver::resolve($period->start_date, $period->company_id);

        // Hitung berapa kali karyawan ini punya pengajuan Izin (kategori Izin Seharian) yang
        // sudah APPROVED dan tanggalnya jatuh di dalam periode Izin ini.
        $count = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', function ($q) use ($izinPeriodStart, $izinPeriodEnd) {
                $q->whereBetween('date', [$izinPeriodStart, $izinPeriodEnd])
                    ->where('category', PermissionTypeCategoryEnum::IZIN_SEHARIAN->value);
            })
            ->count();

        // Tidak ada izin seharian di periode ini - tidak ada yang dipotong, selesai.
        if ($count <= 0) {
            return;
        }

        $rateHarian = $this->rateHarian($user, $payroll, $period, $context);

        if ($rateHarian <= 0) {
            return;
        }

        $amount = round($rateHarian * $count, 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::IZIN_SEHARIAN->label(),
            'type' => PayrollComponentEnum::IZIN_SEHARIAN->type()->value,
            'quantity' => $count,
            'unit' => 'hari',
            'unit_amount' => round($rateHarian, 2),
            'amount' => $amount,
            'source_type' => PayrollComponentEnum::IZIN_SEHARIAN->value,
        ]);

        // Catat sebagai potongan di akumulator payroll berjalan.
        $context->addDeduction($amount);
    }
}
