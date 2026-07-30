<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

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

        $count = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date])
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
