<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Employee\Employee;
use App\Services\Attendance\AttendancePeriodResolver;
use Carbon\Carbon;

/**
 * Potongan per kejadian, TANPA kompensasi/grace (beda dari Keterlambatan) — langsung dipotong
 * sejak kejadian pertama. Dicocokkan lewat `attendance_req_permission_details.category` (kolom
 * didenormalisasi langsung di baris pengajuan, bukan lewat JOIN ke m_permission_types lagi —
 * lihat migration add_category_to_attendance_req_permission_details_table), cuma company yang
 * datanya sudah dikonfirmasi (Holding) yang punya category terisi — company lain otomatis 0
 * baris sampai category-nya diisi. Bisa gabung lebih dari 1 category (mis. Izin Terlambat Masuk
 * + Pulang Cepat + Meninggalkan Jam Kerja Tengah Hari digabung jadi 1 komponen gaji, walau sisi
 * absensi tetap 3 category terpisah).
 *
 * Dua mode nominal, diatur setting `izin_potongan_per_jam` (boolean, per company) - instruksi
 * user 2026-09-29/30, beberapa unit bisnis menghitungnya kelipatan jam, bukan flat:
 * - false (default): `izin_potongan_flat` dipakai FLAT per kejadian, berapa pun lama izinnya.
 * - true: `izin_potongan_flat` dipakai sebagai RATE PER JAM, dikali total jam izin (dari
 *   start_time/end_time tiap baris permissionDetails - kolom ini SELALU terisi buat 3 category
 *   di komponen ini, lihat SubmitPermissionRequest::$wajibJam / PermissionGroupEnum::wajibJam()).
 */
abstract class AbstractIzinFlatCalculator implements PayrollComponentCalculator
{
    /** @return string[] */
    abstract protected function permissionTypeCategories(): array;
    abstract protected function componentValue(): string;
    abstract protected function componentLabel(): string;
    abstract protected function componentType(): string;

    public function calculate(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $flatAmount = Setting::resolve('izin_potongan_flat', $period->company_id, default: 0);

        if ($flatAmount <= 0) {
            return;
        }

        $perJam = Setting::resolve('izin_potongan_per_jam', $period->company_id, default: false);

        // Window pengecekan tanggal izin BUKAN literal start_date/end_date PayrollPeriod (itu
        // diketik bebas admin pas bikin periode gaji, bisa aja 1-31 kalender) - dipakai window
        // "periode Izin" dari Pengaturan Absensi (AttendanceSettingEnum::PERIOD_START_DAY/END_DAY,
        // default 21-20), sama seperti yang dipakai buat kuota Izin - biar konsisten dan tidak ada
        // izin yang "tertinggal" cuma karena periode gaji ditulis beda dari periode absensi
        // company itu (2026-08-02).
        [$izinPeriodStart, $izinPeriodEnd] = AttendancePeriodResolver::resolve($period->start_date, $period->company_id);

        $details = AttendanceRequestPermissionDetails::query()
            ->whereHas('attendanceRequest', fn ($q) => $q
                ->where('user_id', $employee->user_id)
                ->where('type', 'permission')
                ->where('status', AttendanceReqStatus::APPROVED->value))
            ->whereBetween('date', [$izinPeriodStart, $izinPeriodEnd])
            ->whereIn('category', $this->permissionTypeCategories())
            ->get();

        $count = $details->count();

        if ($count <= 0) {
            return;
        }

        if ($perJam) {
            $totalHours = $details->sum(function (AttendanceRequestPermissionDetails $detail) {
                if (! $detail->start_time || ! $detail->end_time) {
                    return 0;
                }

                return Carbon::parse($detail->start_time)->diffInMinutes(Carbon::parse($detail->end_time)) / 60;
            });

            if ($totalHours <= 0) {
                return;
            }

            $amount = $totalHours * (float) $flatAmount;

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $this->componentLabel(),
                'type' => $this->componentType(),
                'quantity' => $totalHours,
                'unit' => 'jam',
                'unit_amount' => $flatAmount,
                'amount' => $amount,
                'note' => sprintf('%.2f jam/Bulan (%dx kejadian)', $totalHours, $count),
                'source_type' => $this->componentValue(),
            ]);

            $context->addDeduction($amount);

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
            'note' => "{$count}x/Bulan",
            'source_type' => $this->componentValue(),
        ]);

        $context->addDeduction($amount);
    }
}
