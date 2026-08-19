<?php

namespace App\Livewire;

use App\Enum\CustomPermissionEnum;
use App\Enum\LsmCourseStatus;
use App\Enum\WidgetPermissionEnum;
use App\Enum\Task\TaskStatus;
use App\Livewire\Concerns\ResolvesDayScheduleSummary;
use App\Livewire\Concerns\ResolvesTodayAttendanceStatus;
use App\Models\Task\Task;
use App\Models\LMS\LmsCourseUsers;
use App\Models\Master\Attendance\Holidays;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Beranda'])]
class Dashboard extends Component
{
    use ResolvesTodayAttendanceStatus;
    use ResolvesDayScheduleSummary;

    /** Bulan yang ditampilkan widget "Jadwal Shift Saya" (Y-m) - state Livewire biasa, bukan
     * #[Url], widget ini bagian dari Dashboard bukan halaman sendiri. */
    public string $calendarMonth = '';

    public function mount(): void
    {
        $this->calendarMonth = now()->format('Y-m');
    }

    public function calendarPrevMonth(): void
    {
        $this->calendarMonth = Carbon::createFromFormat('Y-m', $this->calendarMonth)->subMonthNoOverflow()->format('Y-m');
    }

    public function calendarNextMonth(): void
    {
        $this->calendarMonth = Carbon::createFromFormat('Y-m', $this->calendarMonth)->addMonthNoOverflow()->format('Y-m');
    }

    public function getTodayAttendanceStatusProperty(): array
    {
        return $this->todayAttendanceStatus(Auth::user());
    }

    // Card "Pengajuan Absensi Saya" & "Pelatihan Saya" di home-cards disembunyikan kalau user
    // tidak punya permission ini (dipakai juga sebagai gerbang halaman terkait di
    // config/portal-pages.php) - saat disembunyikan, card di sebelahnya (Presensi/Tugas Saya)
    // melebar mengisi kolom yang kosong, lihat modifier class .no-pengajuan/.no-pelatihan di
    // resources/css/app.css.
    public function getCanPengajuanAbsensiProperty(): bool
    {
        return (bool) Auth::user()?->can(CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value);
    }

    public function getCanPelatihanSayaProperty(): bool
    {
        return (bool) Auth::user()?->can(CustomPermissionEnum::PELATIHAN_SAYA->value);
    }

    public function getCanTugasSayaProperty(): bool
    {
        return (bool) Auth::user()?->can(CustomPermissionEnum::TUGAS_SAYA->value);
    }

    /** Widget "Jadwal Shift Tim" (TeamShiftScheduleCard) - SEBELUMNYA otomatis tampil untuk
     * siapa pun yang punya bawahan, diganti ke permission eksplisit (WidgetPermissionEnum,
     * bukan CustomPermissionEnum - lihat docblock enum itu) 2026-08-19 supaya lebih mudah
     * diatur siapa yang lihat, bukan implisit dari struktur organisasi. */
    public function getCanJadwalShiftTimProperty(): bool
    {
        return (bool) Auth::user()?->can(WidgetPermissionEnum::JADWAL_SHIFT_TIM->value);
    }

    /** Widget "Jadwal Shift Saya" cuma buat karyawan yang beneran punya jadwal tipe Shift -
     * dicek dari User::hasShiftSchedule() (identitas jadwal karyawan, bukan isi calendarWeeks
     * bulan yang lagi ditampilkan - kalau dicek dari situ, widget bisa hilang sendiri pas user
     * navigasi ke bulan yang rosternya kebetulan belum diisi, padahal dia tetap karyawan shift).
     * Bukan permission JADWAL_SHIFT_SAYA lagi (2026-08-15, permission itu tidak pernah di-assign
     * ke role manapun jadi widget-nya tidak pernah muncul walau jadwalnya shift). */
    public function getCanJadwalShiftSayaProperty(): bool
    {
        return (bool) Auth::user()?->hasShiftSchedule();
    }

    /** Schedule (shift) yang lagi dipakai user, buat link "Lihat Detail Jadwal" widget ke
     * ScheduleDetailPage (`presensi.jadwal.show`) - karyawan boleh buka halaman itu buat
     * Schedule ini lewat SchedulePolicy::view() + User::isAssignedToSchedule() (2026-08-15),
     * biarpun bukan admin. Null kalau entah kenapa tidak ketemu (widget sendiri sudah tidak akan
     * tampil di kasus itu, lihat canJadwalShiftSaya). */
    public function getMyScheduleIdProperty(): ?string
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $shiftAssignment = \App\Models\Shift\ScheduleShiftAssignment::where('user_id', $user->id)
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->latest('date')
            ->first();

        if ($shiftAssignment?->shiftType) {
            return $shiftAssignment->shiftType->schedule_id;
        }

        return \App\Models\Shift\ScheduleAssignment::resolveForUser($user, now()->toDateString())?->schedule_id;
    }

    /**
     * Mini-kalender bulan berjalan (bentuk sama dengan JadwalSayaPage::getWeeksProperty()),
     * navigasi lewat calendarPrevMonth()/calendarNextMonth() - murni jadwal RENCANA (bukan
     * status realisasi presensi, itu domain lain, lihat ResolvesTodayAttendanceStatus buat kartu
     * "Presensi Hari Ini" yang terpisah). Tiap sel scheduled bawa 'code' (kode shift, mis. PAGI/
     * MALAM) buat ditampilkan sebagai inisial di kalender - beda dari JadwalSayaPage yang
     * nampilin nama shift penuh, di sini ruang selnya kecil (instruksi user 2026-08-15).
     */
    public function getCalendarWeeksProperty(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $month  = Carbon::createFromFormat('Y-m', $this->calendarMonth);
        $start  = $month->copy()->startOfMonth();
        $end    = $month->copy()->endOfMonth();

        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $holidays = Holidays::query()
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->pluck('name', 'date')
            ->mapWithKeys(fn ($name, $date) => [Carbon::parse($date)->toDateString() => $name]);

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

    public function getCalendarMonthLabelProperty(): string
    {
        return Carbon::createFromFormat('Y-m', $this->calendarMonth)->translatedFormat('F Y');
    }

    /** Legend "Detail Jadwal" - kode shift + warna yang beneran muncul di calendarWeeks bulan
     * ini (bukan daftar statis semua kode shift yang ada, biar legend tidak bengkak buat shift
     * yang bulan ini tidak dipakai user). */
    public function getShiftLegendProperty(): array
    {
        return collect($this->calendarWeeks)
            ->flatten(1)
            ->filter(fn ($day) => $day['is_current_month'] && ($day['schedule']['state'] ?? null) === 'scheduled' && $day['schedule']['code'])
            ->unique(fn ($day) => $day['schedule']['code'])
            ->map(fn ($day) => ['code' => $day['schedule']['code'], 'label' => $day['schedule']['label'], 'detail' => $day['schedule']['detail'], 'color' => $day['schedule']['color']])
            ->values()
            ->all();
    }

    public function getRecentTasksProperty()
    {
        $userId = Auth::id();

        return Task::where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('assigned_to', $userId);
            })
            // Task di dalam Project/Board dikecualikan - kartu ini cuma buat task personal
            // (Todo Saya/Tugas Saya), sama seperti TaskService::scopedTaskQuery(). Task board
            // eksklusif dilihat dari board-nya sendiri, termasuk otomatis tersaring kalau
            // board-nya sudah dihapus/nonaktif (soft-deleted) - tidak perlu cek terpisah.
            ->whereNull('project_id')
            ->whereIn('status', [TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value, TaskStatus::REVIEW->value])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();
    }

    // Pelatihan yang sedang dijalani (in_progress) atau sudah jadi member tapi belum mulai
    // (requested/approved) — completed/canceled sengaja dikecualikan, itu bukan "saat ini".
    // Course yang belum Published (masih Draft) juga dikecualikan - kartu ini cuma buat
    // pelatihan yang beneran sudah bisa diikuti, bukan yang masih disiapkan HC.
    public function getMyTrainingsProperty()
    {
        return LmsCourseUsers::with('course.category')
            ->where('user_id', Auth::id())
            ->whereIn('status', ['requested', 'approved', 'in_progress'])
            ->whereHas('course', fn ($q) => $q->where('status', LsmCourseStatus::Published->value))
            ->orderByDesc('enrolled_at')
            ->limit(4)
            ->get();
    }

    // Sama persis dengan TrainingList::categoryAccent() (halaman /pelatihan) — warna aksen
    // per kategori harus konsisten di kedua tempat, jadi hash-nya disamakan.
    public function categoryAccent(?string $categoryName): string
    {
        $palette = ['navy', 'green', 'rust', 'teal', 'gold'];

        if (! $categoryName) {
            return $palette[0];
        }

        return $palette[crc32($categoryName) % count($palette)];
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
