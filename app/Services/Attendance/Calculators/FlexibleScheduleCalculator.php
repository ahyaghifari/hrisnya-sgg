<?php

namespace App\Services\Attendance\Calculators;

use App\Enum\AttendanceStatus;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Services\Attendance\Calculators\Concerns\ComputesWorkDayWeight;
use Carbon\Carbon;

/**
 * Tahap 5 (Flexible) pipeline access_logs -> jadwal. Beda dari Fixed/Shift: Flexible tidak
 * punya start_time/end_time resmi, cuma jendela check-in (schedules.flexible_checkin_from/
 * until) dan target min/max jam kerja per hari (ScheduleFlexibleDay). early_leave_minutes
 * TETAP tidak relevan secara konsep untuk Flexible (tidak ada end_time resmi buat
 * dibandingkan), tapi late_minutes SEKARANG relevan lewat late_checkin_deadline per-hari
 * (opsional, migration 2026_07_28_200000) - kalau kosong, tidak ada batas jam masuk sama
 * sekali untuk hari itu.
 *
 * Arti kolom (2026-10-04): max_work_minutes = TARGET jam kerja (kelebihannya jadi lembur) dan
 * min_work_minutes = durasi paling pendek yang boleh pulang duluan; tidak ada batas atas jam
 * pulang karena jadwal ini memang fleksibel.
 *
 * min_work_minutes (kalau diisi >0) SEKARANG juga jadi pagar checkout, sama pola dengan
 * FixedScheduleCalculator: checkout yang durasinya di bawah minimum DITOLAK ($entry['check_out']
 * dipaksa null, 'checkout_rejected' => true) - bukan cuma ditandai lewat meets_target=false.
 * Tanpa ini, 2 scan yang cuma berjarak beberapa detik (jari kepencet 2x di device fingerprint)
 * langsung disimpan sebagai check-in+check-out, working_minutes ~0 - dilaporkan user 2026-09-22.
 * Beda dari Fixed, Flexible tidak punya end_time resmi buat jadi penentu "ini memang sudah jam
 * pulang" - jadi checkout di bawah minimum SELALU ditolak di sini (bukan cuma sebelum end_time),
 * ditahan sampai ada scan berikutnya yang bikin durasinya cukup (atau dikoreksi manual/sync ulang).
 */
class FlexibleScheduleCalculator
{
    use ComputesWorkDayWeight;

    public function calculate(array $entries): array
    {
        return array_map(fn (array $entry) => $this->calculateOne($entry), $entries);
    }

    private function calculateOne(array $entry): array
    {
        /** @var ScheduleFlexibleDay $config */
        $config   = $entry['config'];
        $schedule = $entry['schedule'];

        $ci = Carbon::createFromFormat('H:i:s', $entry['check_in']);

        $withinCheckinWindow = true;
        if ($schedule->flexible_checkin_from && $schedule->flexible_checkin_until) {
            $windowFrom  = Carbon::createFromFormat('H:i:s', $schedule->flexible_checkin_from);
            $windowUntil = Carbon::createFromFormat('H:i:s', $schedule->flexible_checkin_until);
            $withinCheckinWindow = $ci->between($windowFrom, $windowUntil);
        }

        $lateMinutes = 0;
        if ($config->late_checkin_deadline) {
            $deadline = Carbon::createFromFormat('H:i:s', $config->late_checkin_deadline);
            $lateMinutes = $ci->gt($deadline) ? (int) $deadline->diffInMinutes($ci) : 0;
        }

        $workingMinutes   = null;
        $overtimeMinutes  = null;
        $meetsTarget      = null;
        $checkOut         = $entry['check_out'];
        $checkoutRejected = false;

        if ($checkOut) {
            $co = Carbon::createFromFormat('H:i:s', $checkOut);
            if ($co->lt($ci)) {
                $co->addDay();
            }

            $rawMinutes = max(0, (int) $ci->diffInMinutes($co) - $config->break_minutes);

            if ($config->min_work_minutes > 0 && $rawMinutes < $config->min_work_minutes) {
                // Durasi belum memenuhi minimum - tolak scan checkout ini (kemungkinan checkout
                // dini/scan ganda), biarkan check_out_time tetap kosong (atau nilai sebelumnya)
                // sampai ada scan berikutnya yang bikin durasinya cukup.
                $checkoutRejected = true;
                $checkOut = null;
            } else {
                // max_work_minutes DI FLEXIBLE = target jam kerja normal (dipasangkan dengan
                // min_work_minutes), BUKAN batas mutlak seperti di Fixed/Shift - kelebihan dari
                // target ini yang jadi kandidat lembur, dibatasi overtime_min/max_minutes dan
                // overtime_break_minutes dikurangkan (sama pola dengan break_minutes kerja biasa).
                $target = $config->max_work_minutes;
                if ($target > 0 && $rawMinutes > $target) {
                    $workingMinutes    = $target;
                    $overtimeCandidate = $rawMinutes - $target;

                    // ">=" (bukan ">") - "minimal X menit" berarti PAS di X menit sudah memenuhi syarat,
                    // bukan harus lebih dari X (2026-08-24, ditemukan pas verifikasi konsistensi dengan
                    // formula overtimeAfter() FixedScheduleCalculator - lihat komentarnya di sana).
                    if ($config->overtime_max_minutes > 0 && $overtimeCandidate >= $config->overtime_min_minutes) {
                        $overtimeMinutes = max(0, min($overtimeCandidate, $config->overtime_max_minutes) - $config->overtime_break_minutes);
                    }
                } else {
                    $workingMinutes = $rawMinutes;
                }

                // Target = max_work_minutes (titik mulai lembur), BUKAN min_work_minutes: min
                // cuma pagar "boleh pulang duluan sejak durasi berapa", jadi pulang di antara
                // min dan max diterima & tersimpan tapi ditandai belum capai target. max
                // kosong/0 = tidak ada target terpisah, jatuh balik ke min (perilaku lama).
                $meetsTarget = ($workingMinutes + ($overtimeMinutes ?? 0)) >= ($target > 0 ? $target : $config->min_work_minutes);
            }
        }

        // $entry + [...] di return TIDAK BISA nimpa key yang $entry sudah punya (union operator
        // pertahankan sisi kiri) - checkout yang ditolak harus di-mutate di $entry sendiri di sini,
        // bukan cuma dititip di array literal bawah (itu sebabnya awalnya lolos tes 1x lalu ternyata
        // check_out lama masih kebawa, ketahuan pas verifikasi ulang).
        $entry['check_out'] = $checkOut;

        // status: early_leave tidak berlaku (lihat docblock class ini), tapi late_minutes SEKARANG
        // bisa mengubah status jadi 'late' sama seperti Fixed, kalau late_checkin_deadline diisi.
        // Sama seperti Fixed, lembur auto-hitung dari raw scan TIDAK mengubah status jadi
        // 'overtime' - itu cuma berlaku untuk pengajuan lembur yang sudah disetujui.
        // half_day_threshold_minutes TIDAK mengubah status - cuma menghasilkan work_day_weight
        // terpisah (dipakai rekap payroll), sama seperti Fixed.
        $status = $lateMinutes > 0 ? AttendanceStatus::LATE->value : AttendanceStatus::PRESENT->value;

        // work_day_weight dibandingkan ke TOTAL kerja aktual (working + overtime), bukan cuma
        // working_minutes yang sudah di-cap ke target - lembur tetap kerja sungguhan.
        $totalMinutes = $workingMinutes === null ? null : $workingMinutes + ($overtimeMinutes ?? 0);

        return $entry + [
            'late_minutes'        => $lateMinutes,
            'early_leave_minutes' => 0,
            'working_minutes'     => $workingMinutes,
            'overtime_minutes'    => $overtimeMinutes,
            'meets_target'        => $meetsTarget,
            'work_day_weight'     => $this->workDayWeight($config, $totalMinutes),
            'status'              => $status,
            'out_of_window'       => ! $withinCheckinWindow,
            'checkout_rejected'   => $checkoutRejected,
        ];
    }
}
