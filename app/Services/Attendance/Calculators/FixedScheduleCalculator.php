<?php

namespace App\Services\Attendance\Calculators;

use App\Enum\AttendanceStatus;
use App\Models\Shift\ScheduleFixedDay;
use App\Services\Attendance\Calculators\Concerns\ComputesWorkDayWeight;
use Carbon\Carbon;

/**
 * Tahap 5 (Fixed) pipeline access_logs -> jadwal. Terima array entri dari
 * AccessLogScheduleReader (sudah punya user/date/check_in/check_out/config), hitung
 * telat/pulang-cepat/jam-kerja/status pakai kolom "Pengaturan Lanjutan" ScheduleFixedDay,
 * kembalikan array hasil. Tidak tahu apa-apa soal access_logs atau resolusi jadwal - reusable
 * untuk sumber input lain selama bentuk entrinya sama.
 *
 * min_work_minutes (opsional, default 0=nonaktif) - kalau durasi kerja checkout ini di bawah
 * minimum, checkout DITOLAK (bukan cuma ditandai): $entry['check_out'] dipaksa null dan
 * 'checkout_rejected' => true dikembalikan, dikonsumsi SyncAccessLogsAttendance supaya tidak
 * menimpa check_out_time attendance_records & tidak mengirim notifikasi checkout palsu.
 */
class FixedScheduleCalculator
{
    use ComputesWorkDayWeight;

    public function calculate(array $entries): array
    {
        return array_map(fn (array $entry) => $this->calculateOne($entry), $entries);
    }

    private function calculateOne(array $entry): array
    {
        /** @var ScheduleFixedDay $config */
        $config = $entry['config'];

        // Scan tunggal yang waktunya sudah >= jam pulang jadwal: karyawan kemungkinan lupa/lewat
        // scan masuk paginya, bukan scan masuk yang kebetulan telat sampai jam pulang (instruksi
        // user 2026-08-10) - diperlakukan sebagai check-out saja. Diputuskan di sini (bukan di
        // reader) karena hanya calculator yang memegang jam pulang jadwalnya.
        if (($entry['scan_count'] ?? 0) === 1 && $entry['check_out'] === null
            && $entry['check_in'] !== null && $entry['check_in'] >= $config->end_time) {
            $entry['check_out'] = $entry['check_in'];
            $entry['check_in'] = null;
        }

        // Tidak ada jam masuk (lupa scan masuk, hanya scan pulang) - tidak ada apa-apa buat
        // dihitung telat/lembur-sebelum/jam-kerja, tapi tetap hadir (instruksi user 2026-08-10) -
        // bukan absen cuma karena kelewatan scan masuk.
        if ($entry['check_in'] === null) {
            return $entry + [
                'late_minutes'        => 0,
                'early_leave_minutes' => 0,
                'working_minutes'     => null,
                'overtime_minutes'    => null,
                'work_day_weight'     => $this->workDayWeight($config, null),
                'status'              => AttendanceStatus::PRESENT->value,
                'out_of_window'       => false,
                'checkout_rejected'   => false,
            ];
        }

        $start = Carbon::createFromFormat('H:i:s', $config->start_time);
        $end   = Carbon::createFromFormat('H:i:s', $config->end_time);

        $ci = Carbon::createFromFormat('H:i:s', $entry['check_in']);

        $earliestCheckIn = $start->copy()->subMinutes($config->checkin_buffer_minutes);
        $latestCheckOut  = $end->copy()->addMinutes($config->checkout_buffer_minutes);

        $lateThreshold = $start->copy()->addMinutes($config->late_tolerance_minutes);
        $lateMinutes   = $ci->gt($lateThreshold) ? (int) $lateThreshold->diffInMinutes($ci) : 0;

        $earlyLeaveMinutes = 0;
        $workingMinutes    = null;
        $overtimeMinutes   = $this->overtimeBefore($config, $start, $ci);
        $outOfWindow       = $ci->lt($earliestCheckIn);
        $checkOut          = $entry['check_out'];
        $checkoutRejected  = false;

        if ($checkOut) {
            $co = Carbon::createFromFormat('H:i:s', $checkOut);

            $earlyThreshold = $end->copy()->subMinutes($config->early_leave_tolerance_minutes);
            $earlyLeaveCandidate = $co->lt($earlyThreshold) ? (int) $co->diffInMinutes($earlyThreshold) : 0;

            $rawWorkingMinutes = max(0, (int) $ci->diffInMinutes($co) - $config->break_minutes);
            if ($config->max_work_minutes) {
                $rawWorkingMinutes = min($rawWorkingMinutes, $config->max_work_minutes);
            }

            $overtimeAfter = $this->overtimeAfter($config, $end, $co);

            if ($config->min_work_minutes > 0 && $co->lt($end) && ($rawWorkingMinutes + $overtimeAfter) < $config->min_work_minutes) {
                // Durasi kerja belum memenuhi minimum DAN scan-nya masih sebelum jam pulang - tolak
                // scan checkout ini (kemungkinan checkout dini/curang), biarkan check_out_time
                // attendance_records tetap kosong (atau nilai sebelumnya) sampai ada scan checkout
                // berikutnya yang bikin durasinya cukup. Beda dari out_of_window yang cuma menandai
                // - di sini datanya sendiri sengaja tidak dipakai. Scan yang SUDAH lewat jam pulang
                // tetap diterima walau durasi belum cukup - itu memang jam pulangnya, bukan checkout
                // dini (instruksi user 2026-08-14).
                $checkoutRejected = true;
                $checkOut = null;
            } else {
                $earlyLeaveMinutes = $earlyLeaveCandidate;
                $workingMinutes    = $rawWorkingMinutes;
                $overtimeMinutes  += $overtimeAfter;
                $outOfWindow       = $outOfWindow || $co->gt($latestCheckOut);
            }
        }

        $entry['check_out'] = $checkOut;

        // status disamakan dengan AttendanceStatus enum (dipakai luas oleh Filament/payroll) -
        // sama seperti AttendanceImportService::computeMetricsFromCheckTimes(): early leave TIDAK
        // mengubah status, cuma tercatat di early_leave_minutes. checkout kosong juga tetap
        // 'present' (karyawan sudah check-in, cuma belum/lupa check-out).
        // ponytail: lembur auto-hitung dari raw scan TIDAK mengubah status jadi 'overtime' -
        // beda dari AttendanceImportService yang cuma pakai status overtime untuk pengajuan yang
        // SUDAH disetujui (AttendanceRequests type=overtime). Auto-deteksi dari scan mentah tidak
        // boleh menyamai kekuatan approval manusia, cuma tercatat di overtime_minutes.
        // half_day_threshold_minutes/absent_threshold_minutes/counted_work_days TIDAK mengubah
        // status (keputusan eksplisit) - cuma menghasilkan work_day_weight terpisah, dipakai
        // rekap payroll buat jumlah hari kerja efektif, bukan status kehadiran harian.
        $status = $lateMinutes > 0 ? AttendanceStatus::LATE->value : AttendanceStatus::PRESENT->value;

        return $entry + [
            'late_minutes'        => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'working_minutes'     => $workingMinutes,
            'overtime_minutes'    => $overtimeMinutes > 0 ? $overtimeMinutes : null,
            'work_day_weight'     => $this->workDayWeight($config, $workingMinutes),
            'status'              => $status,
            'out_of_window'       => $outOfWindow,
            'checkout_rejected'   => $checkoutRejected,
        ];
    }

    /**
     * Lembur SEBELUM jam masuk: check-in lebih awal dari start_time - overtime_before_min,
     * dibatasi jendela overtime_before_max, overtime_before_break dikurangkan dari durasinya
     * (sama pola dengan break_minutes dikurangkan dari jam kerja biasa).
     */
    private function overtimeBefore(ScheduleFixedDay $config, Carbon $start, Carbon $ci): int
    {
        if ($config->overtime_before_max <= 0) {
            return 0;
        }

        $countedFrom = $start->copy()->subMinutes($config->overtime_before_max);
        $countedTo   = $start->copy()->subMinutes($config->overtime_before_min);
        $effectiveCi = $ci->gt($countedFrom) ? $ci : $countedFrom;

        if ($effectiveCi->gte($countedTo)) {
            return 0;
        }

        return max(0, (int) $effectiveCi->diffInMinutes($countedTo) - $config->overtime_before_break);
    }

    /**
     * Lembur SETELAH jam pulang: check-out lebih lambat dari end_time + overtime_after_min,
     * dibatasi jendela overtime_after_max, overtime_after_break dikurangkan dari durasinya.
     *
     * overtime_after_min itu SENDIRI dianggap masa tenggang (grace period) yang TIDAK dibayar -
     * menit lembur yang dihitung cuma yang MELEWATI ambang itu, bukan termasuk ambangnya
     * (co persis di end_time+overtime_after_min = 0 menit lembur, bukan overtime_after_min
     * menit). Ini desain yang disengaja (bukan bug off-by-one) - `lte` di bawah cuma early-return,
     * hasil akhirnya identik dengan `lt` karena countedFrom sudah jadi titik nol hitungan
     * (diffInMinutes(countedFrom, countedFrom) = 0 juga tanpa early-return). Diverifikasi
     * 2026-08-24 setelah sempat dikira bug, JANGAN diubah tanpa mikir ulang - beda desain dari
     * FlexibleScheduleCalculator yang menghitung overtimeCandidate FULL (bukan excess-only)
     * begitu >= overtime_min_minutes.
     */
    private function overtimeAfter(ScheduleFixedDay $config, Carbon $end, Carbon $co): int
    {
        if ($config->overtime_after_max <= 0) {
            return 0;
        }

        $countedFrom  = $end->copy()->addMinutes($config->overtime_after_min);
        $countedTo    = $end->copy()->addMinutes($config->overtime_after_max);
        $effectiveCo  = $co->lt($countedTo) ? $co : $countedTo;

        if ($effectiveCo->lte($countedFrom)) {
            return 0;
        }

        return max(0, (int) $countedFrom->diffInMinutes($effectiveCo) - $config->overtime_after_break);
    }
}
