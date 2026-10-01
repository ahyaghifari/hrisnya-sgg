<?php

namespace App\Services\Shift;

use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;

/**
 * Karyawan yang dipindah ke jadwal Shift lain membawa baris roster lamanya, dan baris itu masih
 * menunjuk shift_type milik jadwal ASAL - di grid jadwal baru sel-nya tidak ketemu (kosong) karena
 * grid mencari lewat shift_type jadwal itu sendiri. Di sini shift_type baris roster mulai
 * $fromDate dipindah ke shift_type jadwal tujuan yang kodenya sama (nama sama sebagai cadangan).
 * Baris Libur (off_day) tidak punya shift_type, tidak disentuh. Yang tidak ada padanannya
 * dibiarkan apa adanya dan dilaporkan di 'unmapped'.
 *
 * ponytail: kolom draft_shift_type_id (pengajuan in_review) tidak ikut dipindah.
 */
class RosterScheduleRemapper
{
    /**
     * @param  array<int, string>  $userIds
     * @return array{moved: int, unmapped: int}
     */
    public function remap(Schedule $to, array $userIds, string $fromDate): array
    {
        $targets = $to->shiftTypes;
        $byCode = $targets->keyBy(fn ($t) => mb_strtolower($t->code));
        $byName = $targets->keyBy(fn ($t) => mb_strtolower($t->name));

        $rows = ScheduleShiftAssignment::withoutGlobalScope('company')
            ->whereIn('user_id', $userIds)
            ->whereDate('date', '>=', $fromDate)
            ->whereNotNull('shift_type_id')
            ->whereHas('shiftType', fn ($q) => $q->where('schedule_id', '!=', $to->id))
            ->with('shiftType')
            ->get();

        $moved = $unmapped = 0;

        foreach ($rows as $row) {
            $match = $byCode->get(mb_strtolower($row->shiftType->code)) ?? $byName->get(mb_strtolower($row->shiftType->name));

            if (! $match) {
                $unmapped++;

                continue;
            }

            $row->update(['shift_type_id' => $match->id]);
            $moved++;
        }

        return ['moved' => $moved, 'unmapped' => $unmapped];
    }
}
