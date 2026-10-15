<?php

namespace App\Services\Attendance\Calculators;

use App\Enum\AttendanceStatus;
use Carbon\Carbon;

/**
 * Jadwal IBS (Instalasi Bedah Sentral): dalam satu hari bisa ada beberapa kali masuk dan pulang,
 * tanpa jam baku. Scan dipasangkan berurutan (1 = masuk, 2 = pulang, 3 = masuk, dst) dan jam kerja
 * adalah jumlah durasi tiap pasangan - jeda antar pasangan TIDAK dihitung.
 *
 * Aturan (lihat knowledge/markdown/rencana-tipe-jadwal-ibs.md):
 * - scan ganjil = pasangan terbuka, 0 menit, "belum pulang";
 * - pasangan yang melewati tengah malam dimiliki TANGGAL SCAN MASUK;
 * - tidak ada hitung telat/pulang cepat dan tidak ada lembur otomatis (lembur lewat pengajuan);
 * - status selalu hadir.
 *
 * ponytail: pasangan dianggap menyambung hanya kalau selisih scan <= MAX_PAIR_SECONDS; di atas itu
 * scan masuk sebelumnya dibiarkan terbuka (lupa scan pulang), supaya satu scan lupa tidak
 * membalik pasangan semua hari sesudahnya. Naikkan batasnya kalau ada operasi yang lebih lama.
 */
class IbsScheduleCalculator
{
    public const MAX_PAIR_SECONDS = 12 * 3600;

    /**
     * Pasangkan scan berurutan menjadi sesi. $scans = datetime "Y-m-d H:i:s" urut naik.
     *
     * @param  array<int, string>  $scans
     * @return array<int, array{date: string, in: string, out: ?string, minutes: int}> date = tanggal scan masuk
     */
    public static function pair(array $scans): array
    {
        $sessions = [];
        $open = null;

        foreach ($scans as $scan) {
            if ($open === null) {
                $open = $scan;

                continue;
            }

            if (Carbon::parse($open)->diffInSeconds(Carbon::parse($scan)) <= self::MAX_PAIR_SECONDS) {
                $sessions[] = self::session($open, $scan);
                $open = null;

                continue;
            }

            // Terlalu jauh: scan masuk lama tidak pernah ditutup, scan ini mulai pasangan baru.
            $sessions[] = self::session($open, null);
            $open = $scan;
        }

        if ($open !== null) {
            $sessions[] = self::session($open, null);
        }

        return $sessions;
    }

    private static function session(string $in, ?string $out): array
    {
        return [
            'date' => substr($in, 0, 10),
            'in' => substr($in, 11, 8),
            'out' => $out === null ? null : substr($out, 11, 8),
            'minutes' => $out === null ? 0 : intdiv((int) Carbon::parse($in)->diffInSeconds(Carbon::parse($out)), 60),
        ];
    }

    /**
     * Entri dari AccessLogScheduleReader yang membawa 'sessions' (hasil pair() untuk tanggal itu).
     */
    public function calculate(array $entries): array
    {
        return array_map(function (array $entry) {
            $sessions = $entry['sessions'] ?? [];
            $closed = array_values(array_filter($sessions, fn (array $s) => $s['out'] !== null));

            $entry['check_in'] = $sessions[0]['in'] ?? $entry['check_in'] ?? null;
            // Pulang terakhir yang SUDAH tercatat; pasangan terbuka di ujung tidak punya jam pulang.
            $entry['check_out'] = $closed === [] ? null : end($closed)['out'];

            return $entry + [
                'late_minutes'        => 0,
                'early_leave_minutes' => 0,
                'working_minutes'     => array_sum(array_column($sessions, 'minutes')),
                'overtime_minutes'    => null,
                'meets_target'        => null,
                'work_day_weight'     => null,
                'status'              => AttendanceStatus::PRESENT->value,
                'out_of_window'       => false,
                'scan_sessions'       => $sessions,
            ];
        }, $entries);
    }
}
