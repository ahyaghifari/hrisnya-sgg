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
     * @return array{state: 'holiday'|'off'|'scheduled'|'none', label: ?string, detail: ?string, color: ?string}
     */
    protected function resolveDaySchedule(ScheduleResolver $resolver, User $user, string $date, ?string $holidayName): array
    {
        $resolved = $resolver->resolveForUserDate($user, $date);

        if ($holidayName) {
            return ['state' => 'holiday', 'label' => $holidayName, 'detail' => null, 'color' => null];
        }

        if (! $resolved) {
            return ['state' => 'none', 'label' => null, 'detail' => null, 'color' => null];
        }

        if ($resolved['is_off_day'] ?? false) {
            return ['state' => 'off', 'label' => 'Libur', 'detail' => null, 'color' => null];
        }

        $config = $resolved['config'] ?? null;

        return match ($resolved['type']) {
            'shift' => [
                'state' => 'scheduled',
                'label' => $config?->name,
                'detail' => $config ? substr($config->start_time, 0, 5) . '–' . substr($config->end_time, 0, 5) : null,
                'color' => $config?->color,
            ],
            'fixed' => [
                'state' => 'scheduled',
                'label' => $resolved['schedule']->name ?? 'Jadwal Tetap',
                'detail' => $config ? substr($config->start_time, 0, 5) . '–' . substr($config->end_time, 0, 5) : null,
                'color' => null,
            ],
            'flexible' => [
                'state' => 'scheduled',
                'label' => $resolved['schedule']->name ?? 'Jadwal Fleksibel',
                'detail' => 'Fleksibel',
                'color' => null,
            ],
            default => ['state' => 'none', 'label' => null, 'detail' => null, 'color' => null],
        };
    }
}
