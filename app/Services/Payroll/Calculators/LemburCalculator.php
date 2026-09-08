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
 * dari jam lembur (2-4 jam → 3,5-7,5x, di bawah minimum ditolak). Satu-satunya metode Lembur di sistem ini —
 * mekanisme shift-based (ShiftOvertimeCosts) lama sudah tidak dipakai lagi.
 *
 * Batas minimum jam (`lembur_minimum_jam`, company-scoped, default 2) DIBACA dari setting yang
 * sama yang dipakai buat label di slip gaji ("Lembur >X Jam", lihat PayrollService::
 * componentRows()) - sebelumnya slip mencetak label dari setting tapi hitungannya hardcode 2,
 * jadi bisa beda dari yang tertulis kalau company override settingnya.
 *
 * Lembur di bawah `lembur_minimum_jam` DITOLAK (tidak dihitung sama sekali), bukan di-clamp naik
 * ke minimum lagi (2026-08-03) — harus benar-benar capai minimum jam dulu baru dapat lembur.
 */
class LemburCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        // "per_pangkat" = rumus lama Excel Holding (base rate per pangkat / (hari x 7) x multiplier).
        // "flat_per_jam" = 1 rate flat per company x jam desimal sampai menit:
        //   (rate x jam bulat) + (rate/60 x sisa menit). Batas minimum jam tetap dipakai keduanya.
        $metode = (string) Setting::resolve('lembur_metode', $period->company_id, default: 'per_pangkat');

        $baseRate = $metode === 'flat_per_jam'
            ? (float) Setting::resolve('lembur_rate_per_jam', $period->company_id, default: 0)
            : (float) ($user->profile?->pangkat?->lemburRate?->amount_per_hour ?? 0);

        if ($baseRate <= 0) {
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
                $result = $metode === 'flat_per_jam'
                    ? $this->calculateDetailFlat($detail, $baseRate, $minimumJam)
                    : $this->calculateDetail($detail, $baseRate, $minimumJam);

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

        if ($hours <= 0 || $hours < $minimumJam) {
            return null;
        }

        // Multiplier clamp maksimal di 4 jam (belum ada permintaan buat itu configurable juga).
        $multiplier = (min($hours, 4) * 2) - 0.5;
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

    /**
     * Metode "flat_per_jam": (rate x jam bulat) + (rate/60 x sisa menit). Contoh 2,5 jam @ 10rb
     * = (10000 x 2) + (10000/60 x 30) = 25.000. Minimum jam dicek dari jam bulat, sama seperti
     * metode per_pangkat (lembur < minimum ditolak, bukan di-clamp).
     *
     * @return array{amount: float, hours: int, note: string}|null
     */
    private function calculateDetailFlat(AttendanceRequestOvertimeDetails $detail, float $ratePerJam, int $minimumJam): ?array
    {
        $date = Carbon::parse($detail->date);
        $totalHours = (float) $detail->total_hours;
        $hours = (int) floor($totalHours);
        $minutes = (int) round(($totalHours - $hours) * 60);

        if ($hours <= 0 || $hours < $minimumJam) {
            return null;
        }

        $amount = round($ratePerJam * $hours + ($ratePerJam / 60) * $minutes, 2);

        if ($amount <= 0) {
            return null;
        }

        $dateLabel = $date->locale('id')->isoFormat('D MMMM YYYY');
        $durasi = $minutes > 0 ? "{$hours} jam {$minutes} menit" : "{$hours} jam";

        return [
            'amount' => $amount,
            'hours' => $hours,
            'note' => "{$dateLabel} ({$durasi}): Rp" . number_format($amount, 0, ',', '.'),
        ];
    }
}
