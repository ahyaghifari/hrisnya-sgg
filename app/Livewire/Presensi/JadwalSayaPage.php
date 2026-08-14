<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\ResolvesDayScheduleSummary;
use App\Models\Master\Attendance\Holidays;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kalender bulanan jadwal PRIBADI (user yang login) - padanan personal dari
 * ScheduleShiftRosterTab (grid admin, semua karyawan x semua tanggal). Dipakai perawat/karyawan
 * lain untuk lihat jadwal shift mereka sendiri sebelum mengajukan tukar shift (Fase 2) - baru
 * bagian "lihat", belum ada aksi apapun di sini.
 *
 * Sengaja TIDAK menimpa overlay cuti/izin/hadir (beda dari ResolvesTodayAttendanceStatus yang
 * dipakai kartu "Presensi Hari Ini") - kalender ini murni jadwal YANG DIRENCANAKAN, bukan
 * status realisasi presensi.
 */
#[Layout('components.layout', ['title' => 'Jadwal Shift Saya'])]
class JadwalSayaPage extends Component
{
    use ResolvesDayScheduleSummary;

    #[Url]
    public string $month;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(\App\Enum\CustomPermissionEnum::JADWAL_SHIFT_SAYA->value), 403);

        $this->month = $this->month ?? now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonthNoOverflow()->format('Y-m');
    }

    public function goToToday(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function getMonthLabelProperty(): string
    {
        return Carbon::createFromFormat('Y-m', $this->month)->translatedFormat('F Y');
    }

    /**
     * Grid kalender penuh (minggu x hari, Minggu-Sabtu) - termasuk sel "bantalan" dari bulan
     * sebelum/sesudah supaya barisnya rapi 7 kolom, ditandai is_current_month=false biar
     * ditampilkan pudar di view (tidak diresolve jadwalnya, cuma pengisi grid).
     *
     * @return array<int, array<int, array{date: Carbon, is_current_month: bool, is_today: bool, schedule: ?array}>>
     */
    public function getWeeksProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $holidays = Holidays::query()
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->pluck('name', 'date')
            ->mapWithKeys(fn ($name, $date) => [Carbon::parse($date)->toDateString() => $name]);

        $user = Auth::user();
        $resolver = app(ScheduleResolver::class);
        $today = now()->toDateString();

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dateStr = $cursor->toDateString();
                $isCurrentMonth = $cursor->month === $start->month;

                $week[] = [
                    'date' => $cursor->copy(),
                    'is_current_month' => $isCurrentMonth,
                    'is_today' => $dateStr === $today,
                    'schedule' => $isCurrentMonth
                        ? $this->resolveDaySchedule($resolver, $user, $dateStr, $holidays->get($dateStr))
                        : null,
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    public function render()
    {
        return view('livewire.presensi.jadwal-saya-page');
    }
}
