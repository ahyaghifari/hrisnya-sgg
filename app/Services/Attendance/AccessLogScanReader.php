<?php

namespace App\Services\Attendance;

use App\Models\Master\Attendance\AttendanceDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lapisan paling bawah pipeline presensi: scan mentah access_logs -> scan BERSIH. Tidak tahu apa
 * pun soal jadwal. Dipakai AccessLogScheduleReader (scan baru/rentang) dan ShiftAttendanceReader
 * (scan konteks shift: kemarin, besok, rantai malam berturut-turut) supaya aturan "bersih"-nya
 * satu: tap ganda dibuang, scan dari device yang tidak boleh dipakai user dibuang.
 */
class AccessLogScanReader
{
    /**
     * Dua scan (nip, tanggal) yang jaraknya <= sekian DETIK dianggap SATU tap fisik - device
     * fingerprint/kartu sering mencatat 1 tap jadi 2-3 baris berselang 1-2 detik (14:02:03 dan
     * 14:02:04: yang dipakai 14:02:03). Tanpa ini, tap ganda pas jam masuk langsung jadi pasangan
     * check-in + check-out berdurasi ~0 (dilaporkan user 2026-09-22).
     *
     * 60 detik: jauh di atas pantulan device (1-2 detik), tapi masih jauh DI BAWAH jarak scan sah
     * paling rapat yang mungkin (check-in vs check-out, atau pulang shift normal lalu masuk extra
     * shift - menit-an, bukan detik).
     */
    private const DUPLICATE_SCAN_SECONDS = 60;

    /** Nama device aktif terdaftar (flip: nama => index) - dibaca sekali per instance. */
    private ?Collection $registeredDevices = null;

    /**
     * Scan bersih 1 user di [from, to], urut waktu. Scan dari device yang tidak boleh dipakai user
     * itu sudah dibuang (tidak dilaporkan - pelapornya AccessLogScheduleReader lewat scan baru).
     */
    public function forUser(User $user, string $from, string $to): Collection
    {
        $rows = DB::connection(config('database.access_log_connection'))->table('access_logs')
            ->where('employee_id', (string) $user->nip)
            ->whereBetween('access_date', [$from, $to])
            ->orderBy('access_datetime')
            ->get();

        foreach ($rows as $row) {
            $row->user = $user;
        }

        return $this->splitRejectedDevices($this->dropRepeatedTaps($rows))[0];
    }

    /**
     * Buang scan yang cuma pantulan tap sebelumnya (lihat DUPLICATE_SCAN_SECONDS). Baris PERTAMA
     * tiap rentetan yang dipertahankan - itu waktu tap sebenarnya; sisanya diabaikan.
     *
     * @param  Collection  $rows  baris access_logs mentah, urut access_datetime
     */
    public function dropRepeatedTaps(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn ($row) => $row->employee_id . '|' . $row->access_date)
            ->map(function (Collection $rowsForDay) {
                $kept = collect();
                $lastKeptAt = null;

                foreach ($rowsForDay as $row) {
                    $at = Carbon::parse($row->access_date . ' ' . $row->access_time);

                    if ($lastKeptAt !== null && $lastKeptAt->diffInSeconds($at) <= self::DUPLICATE_SCAN_SECONDS) {
                        continue;
                    }

                    $kept->push($row);
                    $lastKeptAt = $at;
                }

                return $kept;
            })
            ->flatten(1)
            ->sortBy(fn ($row) => $row->access_date . ' ' . $row->access_time)
            ->values();
    }

    /**
     * Pisahkan scan dari device yang TIDAK TERDAFTAR - diblokir total, tidak ikut dihitung/disimpan,
     * dikabari lewat rejected_device. Device yang sah = device aktif di attendance_devices, SIAPA
     * PUN pemiliknya: karyawan boleh absen di device company/lokasi lain (instruksi user 2026-10-10,
     * menggantikan aturan "hanya device company sendiri / yang di-assign ke user"). Tanggal dan user
     * tidak ikut diperiksa. Baris lama tanpa device_name lolos.
     *
     * rejected_device dikelompokkan per (user, tanggal) HANYA supaya notifikasinya satu per hari.
     *
     * @param  Collection  $withUser  baris access_logs yang sudah punya ->user
     * @return array{0: Collection, 1: array<int, array>} [baris lolos, entri ditolak]
     */
    public function splitRejectedDevices(Collection $withUser): array
    {
        $registered = $this->registeredDevices ??= AttendanceDevice::query()->where('is_active', true)->pluck('name')->flip();

        $kept = collect();
        $rejectedRows = collect();

        foreach ($withUser as $row) {
            $device = $row->device_name ?? null;

            blank($device) || $registered->has($device) ? $kept->push($row) : $rejectedRows->push($row);
        }

        $rejected = $rejectedRows
            ->groupBy(fn ($row) => $row->employee_id . '|' . $row->access_date)
            ->map(fn (Collection $dayRows) => [
                'employee_id'  => $dayRows->first()->user->nip,
                'date'         => $dayRows->first()->access_date,
                'check_in'     => $dayRows->first()->access_time,
                'check_out'    => $dayRows->count() > 1 ? $dayRows->last()->access_time : null,
                'scan_count'   => $dayRows->count(),
                'device_names' => $dayRows->pluck('device_name')->filter()->unique()->values()->all(),
                'user'         => $dayRows->first()->user,
            ])
            ->values()
            ->all();

        return [$kept->values(), $rejected];
    }
}
