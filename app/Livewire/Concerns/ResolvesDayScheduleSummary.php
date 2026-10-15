<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\Attendance\ScheduleResolver;

/**
 * Ringkasan jadwal 1 (user, tanggal) - state/label/detail/warna, dipakai bareng oleh
 * JadwalSayaPage (kalender bulanan penuh) dan Dashboard (widget "Jadwal Shift Saya" ringkas)
 * supaya logic-nya tidak dobel. Beda dari ResolvesTodayAttendanceStatus: yang ini murni jadwal
 * YANG DIRENCANAKAN (tidak menimpa cuti/izin/hadir), dan bisa dipanggil untuk tanggal manapun -
 * bukan cuma "hari ini".
 */
trait ResolvesDayScheduleSummary
{
    /**
     * @return array{state: 'holiday'|'off'|'scheduled'|'none', label: ?string, detail: ?string, color: ?string, type: ?string, code: ?string}
     */
    protected function resolveDaySchedule(ScheduleResolver $resolver, User $user, string $date, ?string $holidayName): array
    {
        $resolved = $resolver->resolveForUserDate($user, $date);

        if ($holidayName) {
            return ['state' => 'holiday', 'label' => $holidayName, 'detail' => null, 'color' => null, 'type' => $resolved['type'] ?? null, 'code' => null];
        }

        if (! $resolved) {
            return ['state' => 'none', 'label' => null, 'detail' => null, 'color' => null, 'type' => null, 'code' => null];
        }

        // 'type' (fixed/flexible/shift) diteruskan apa adanya walau off_day/holiday — dipakai
        // Dashboard::canJadwalShiftSaya buat cek "karyawan ini beneran jadwal shift atau bukan",
        // bukan cuma nebak dari isi 7 hari yang kebetulan ada 'scheduled'.
        if ($resolved['is_off_day'] ?? false) {
            return ['state' => 'off', 'label' => 'Libur', 'detail' => null, 'color' => null, 'type' => $resolved['type'], 'code' => null];
        }

        $config = $resolved['config'] ?? null;

        return match ($resolved['type']) {
            // 'code' (mis. PAGI/SIANG/MALAM) cuma ada di shift - dipakai Dashboard widget buat
            // tampilkan inisial di sel kalender yang kecil, bukan nama shift penuh.
            'shift' => [
                'state' => 'scheduled',
                'label' => $config?->name,
                'detail' => $config ? substr($config->start_time, 0, 5) . '–' . substr($config->end_time, 0, 5) : null,
                'color' => $config?->color,
                'type' => 'shift',
                'code' => $config?->code,
            ],
            'fixed' => [
                'state' => 'scheduled',
                'label' => $resolved['schedule']->name ?? 'Jadwal Tetap',
                'detail' => $config ? substr($config->start_time, 0, 5) . '–' . substr($config->end_time, 0, 5) : null,
                'color' => null,
                'type' => 'fixed',
                'code' => null,
            ],
            'flexible' => [
                'state' => 'scheduled',
                'label' => $resolved['schedule']->name ?? 'Jadwal Fleksibel',
                'detail' => 'Fleksibel',
                'color' => null,
                'type' => 'flexible',
                'code' => null,
            ],
            'ibs' => [
                'state' => 'scheduled',
                'label' => $resolved['schedule']->name ?? 'Jadwal IBS',
                'detail' => 'Mengikuti scan',
                'color' => null,
                'type' => 'ibs',
                'code' => null,
            ],
            default => ['state' => 'none', 'label' => null, 'detail' => null, 'color' => null, 'type' => null, 'code' => null],
        };
    }
}
