<?php

namespace App\Livewire;

use App\Models\Shift\ExtraShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Services\Attendance\AttendaceReqService;
use Livewire\Component;

/**
 * Kartu ringkasan pengajuan absensi: Jumlah Izin & Jumlah Lembur (jumlah PENGAJUAN yang dibuat
 * dalam periode ini) + Extra Shift (jumlah extra shift + double shift yang DISETUJUI pada
 * periode ini; cuma tampil untuk karyawan yang punya jadwal Shift, dengan rincian extra vs double
 * di popover hover). "Periode ini" = periode absensi perusahaan (AttendancePeriodResolver,
 * mengikuti cutoff), bukan bulan kalender.
 */
class AttendanceStatCards extends Component
{
    public bool $compact = false;

    // Default diri sendiri; halaman profil karyawan (MyStaff/Kepegawaian) mengoper id orang lain.
    public ?string $userId = null;

    public function render(AttendaceReqService $service)
    {
        $user = ($this->userId ? User::find($this->userId) : null) ?? auth()->user();

        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve(now(), $user->company_id);
        $from = $periodStart->copy()->startOfDay();
        $to = $periodEnd->copy()->endOfDay();

        $requests = $service->getListAttendanceRequestsByUserId($user->id)
            ->filter(fn ($r) => $r->created_at?->between($from, $to));

        $extraShift = null;
        if ($user->hasShiftSchedule()) {
            $byCategory = ExtraShiftAssignment::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->pluck('category')
                ->countBy();

            $extra = (int) ($byCategory[ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT] ?? 0);
            $double = (int) ($byCategory[ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT] ?? 0);
            $extraShift = ['extra' => $extra, 'double' => $double, 'total' => $extra + $double];
        }

        return view('livewire.attendance-stat-cards', [
            'izinCount' => $requests->where('type', 'permission')->count(),
            'lemburCount' => $requests->where('type', 'overtime')->count(),
            'extraShift' => $extraShift,
        ]);
    }
}
