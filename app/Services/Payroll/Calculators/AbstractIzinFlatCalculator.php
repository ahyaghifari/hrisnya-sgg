<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodResolver;

/**
 * Potongan flat per kejadian, TANPA kompensasi/grace (beda dari Keterlambatan) — langsung
 * dipotong sejak kejadian pertama. Dicocokkan lewat `attendance_req_permission_details.category`
 * (kolom didenormalisasi langsung di baris pengajuan, bukan lewat JOIN ke m_permission_types
 * lagi — lihat migration add_category_to_attendance_req_permission_details_table), cuma
 * company yang datanya sudah dikonfirmasi (Holding) yang punya category terisi — company lain
 * otomatis 0 baris sampai category-nya diisi. Bisa gabung lebih dari 1 category (mis. Izin
 * Terlambat Masuk + Pulang Cepat + Meninggalkan Jam Kerja Tengah Hari digabung jadi 1 komponen
 * gaji, walau sisi absensi tetap 3 category terpisah).
 */
abstract class AbstractIzinFlatCalculator implements PayrollComponentCalculator
{
    /** @return string[] */
    abstract protected function permissionTypeCategories(): array;
    abstract protected function componentValue(): string;
    abstract protected function componentLabel(): string;
    abstract protected function componentType(): string;

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $flatAmount = Setting::resolve('izin_potongan_flat', $period->company_id, default: 0);

        if ($flatAmount <= 0) {
            return;
        }

        // Window pengecekan tanggal izin BUKAN literal start_date/end_date PayrollPeriod (itu
        // diketik bebas admin pas bikin periode gaji, bisa aja 1-31 kalender) - dipakai window
        // "periode Izin" dari Pengaturan Absensi (AttendanceSettingEnum::PERIOD_START_DAY/END_DAY,
        // default 21-20), sama seperti yang dipakai buat kuota Izin - biar konsisten dan tidak ada
        // izin yang "tertinggal" cuma karena periode gaji ditulis beda dari periode absensi
        // company itu (2026-08-02).
        [$izinPeriodStart, $izinPeriodEnd] = AttendancePeriodResolver::resolve($period->end_date, $period->company_id);

        $count = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', function ($q) use ($izinPeriodStart, $izinPeriodEnd) {
                $q->whereBetween('date', [$izinPeriodStart, $izinPeriodEnd])
                    ->whereIn('category', $this->permissionTypeCategories());
            })
            ->count();

        if ($count <= 0) {
            return;
        }

        $amount = $count * (float) $flatAmount;

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => $this->componentLabel(),
            'type' => $this->componentType(),
            'quantity' => $count,
            'unit' => 'kejadian',
            'unit_amount' => $flatAmount,
            'amount' => $amount,
            'source_type' => $this->componentValue(),
        ]);

        $context->addDeduction($amount);
    }
}
