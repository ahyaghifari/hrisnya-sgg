<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodResolver;
use Carbon\Carbon;

/**
 * Rumus lama Excel Holding (analisis-payroll-4 §3.6) — "TL"/"TM" (Team Leader/Team Member)
 * awalnya cuma 2 base rate tetap (3,5jt/3jt), sekarang digeneralisasi jadi 1 base rate per
 * pangkat apa pun (`pangkat_lembur_rates.amount_per_hour`, diisi lewat Mapping Komponen Gaji,
 * pola sama Gaji Pokok). Per kejadian lembur: base rate ÷ (hari dalam bulan × 7) × multiplier
 * dari jam lembur (clamp minimum-4 jam → 3,5-7,5x). Satu-satunya metode Lembur di sistem ini —
 * mekanisme shift-based (ShiftOvertimeCosts) lama sudah tidak dipakai lagi.
 *
 * Batas minimum jam (`lembur_minimum_jam`, company-scoped, default 2) DIBACA dari setting yang
 * sama yang dipakai buat label di slip gaji ("Lembur >X Jam", lihat PayrollService::
 * componentRows()) - sebelumnya slip mencetak label dari setting tapi hitungannya hardcode 2,
 * jadi bisa beda dari yang tertulis kalau company override settingnya.
 */
class LemburCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $baseRate = $user->profile?->pangkat?->lemburRate?->amount_per_hour;

        if (! $baseRate || $baseRate <= 0) {
            return;
        }

        $minimumJam = (int) Setting::resolve('lembur_minimum_jam', $period->company_id, default: 2);

        // Window absensi (21-20 default, dari Pengaturan Absensi) - BUKAN literal start_date/
        // end_date PayrollPeriod, sama fix-nya kayak kalkulator Izin/Keterlambatan (2026-08-02).
        [$attendancePeriodStart, $attendancePeriodEnd] = AttendancePeriodResolver::resolve($period->start_date, $period->company_id);

        $overtimeRequests = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'overtime')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('overtimeDetails', function ($q) use ($attendancePeriodStart, $attendancePeriodEnd) {
                $q->whereBetween('date', [$attendancePeriodStart, $attendancePeriodEnd]);
            })
            ->with(['overtimeDetails' => function ($q) use ($attendancePeriodStart, $attendancePeriodEnd) {
                $q->whereBetween('date', [$attendancePeriodStart, $attendancePeriodEnd]);
            }])
            ->get();

        // Digabung jadi 1 baris payroll_items per payroll (bukan 1 baris per tanggal lembur lagi,
        // 2026-08-02) - nama baris cukup "Lembur (Dihitung > X jam)" tanpa daftar tanggal, rincian
        // per kejadian tetap ada di note.
        $totalAmount = 0.0;
        $totalHours = 0;
        $noteLines = [];

        foreach ($overtimeRequests as $request) {
            foreach ($request->overtimeDetails as $detail) {
                $result = $this->calculateDetail($detail, (float) $baseRate, $minimumJam);

                if (! $result) {
                    continue;
                }

                $totalAmount += $result['amount'];
                $totalHours += $result['hours'];
                $noteLines[] = $result['note'];
            }
        }

        if ($totalAmount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::LEMBUR->defaultRowName($period->company_id),
            'type' => PayrollComponentEnum::LEMBUR->type()->value,
            'quantity' => $totalHours,
            'unit' => 'jam',
            'unit_amount' => round($baseRate, 2),
            'amount' => round($totalAmount, 2),
            'note' => implode('; ', $noteLines),
            'source_type' => PayrollComponentEnum::LEMBUR->value,
        ]);

        $context->addEarning(round($totalAmount, 2));
    }

    /** @return array{amount: float, hours: int, note: string}|null */
    private function calculateDetail(AttendanceRequestOvertimeDetails $detail, float $baseRate, int $minimumJam): ?array
    {
        $date = Carbon::parse($detail->date);
        $hours = (int) floor((float) $detail->total_hours); // ROUNDDOWN(jam, 0) di Excel

        if ($hours <= 0) {
            return null;
        }

        // Multiplier meng-clamp jam ke minimum settingan (lembur di bawah itu tetap dihitung
        // setara jam minimum, bukan ditolak) — perilaku asli Excel, bukan bug. Maksimal tetap
        // dikunci di 4 jam (belum ada permintaan buat itu configurable juga).
        $multiplier = (min(max($hours, $minimumJam), 4) * 2) - 0.5;
        $divisor = $date->daysInMonth * 7;
        $amount = round($baseRate / $divisor * $multiplier, 2);

        if ($amount <= 0) {
            return null;
        }

        $dateLabel = $date->locale('id')->isoFormat('D MMMM YYYY');

        return [
            'amount' => $amount,
            'hours' => $hours,
            'note' => "{$dateLabel} ({$hours} jam): Rp" . number_format($amount, 0, ',', '.'),
        ];
    }
}
