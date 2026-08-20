<?php

namespace App\Livewire\Presensi\Concerns;

use App\Models\Shift\ShiftSwapRequests;

/** Format tampilan 1 baris riwayat tukar shift - dipakai bareng MyShiftSwapRequests (halaman
 * penuh) dan ShiftSwapHistorySheet (modal ringkas di widget Dashboard), biar label/warna status
 * tidak dobel ditulis di 2 tempat. */
trait FormatsShiftSwapRequest
{
    public function keterangan(ShiftSwapRequests $record): string
    {
        $req = $record->reqSchedule?->shiftType?->name . ' (' . $record->req_date->translatedFormat('d M') . ')';

        if ($record->targetSchedule) {
            return $req . ' ↔ ' . $record->targetSchedule->shiftType?->name . ' (' . $record->target_date->translatedFormat('d M') . ')'
                . ' dengan ' . ($record->target?->name ?? '-');
        }

        return $req . ' → diserahkan ke ' . ($record->target?->name ?? '-');
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'in_review', 'pending' => 'Diproses',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => $status,
        };
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'approved' => 'badge-approved',
            'in_review', 'pending' => 'badge-pending',
            'rejected' => 'badge-rejected',
            default => 'badge-cancelled',
        };
    }
}
