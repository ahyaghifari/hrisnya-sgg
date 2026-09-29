<?php

namespace App\Livewire;

use App\Enum\CustomPermissionEnum;
use App\Enum\LsmCourseStatus;
use App\Enum\WidgetPermissionEnum;
use App\Enum\Task\TaskStatus;
use App\Livewire\Concerns\ResolvesDayScheduleSummary;
use App\Livewire\Concerns\ResolvesTodayAttendanceStatus;
use App\Models\Task\Task;
use App\Models\Task\TaskProject;
use App\Models\LMS\LmsCourseUsers;
use App\Models\Master\Attendance\Holidays;
use App\Models\Shift\ShiftSwapRequests;
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
     * Schedule ini lewat SchedulePolicy::view() (Kepala Ruangan / karyawan di roster, jadwal shift),
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
        $swapDates = $this->getSwapDates($user, $gridStart->toDateString(), $gridEnd->toDateString());

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
                    'is_swap' => $isCurrentMonth && isset($swapDates[$dateStr]),
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    /** Tanggal (dalam rentang grid kalender yang lagi ditampilkan) yang jadwal $user SENDIRI
     * terkait pengajuan tukar shift in_review/approved - dipakai taruh ikon tukar kecil di sel
     * widget "Jadwal Shift Saya", sama makna dengan ikon di grid Detail Penjadwalan (lihat
     * ScheduleShiftRosterTab::getSwapCellsProperty()), cuma disaring buat 1 user di sini.
     * Sengaja baca user_id dari relasi reqSchedule/targetSchedule LANGSUNG (bukan kolom
     * requester_id/target_id di shift_swap_requests) - begitu approved & mode 'move'/
     * 'move_over_off_day', executeApprovedSwap() mengubah user_id baris schedule_shift_assignments
     * itu sendiri, jadi kolom requester_id/target_id jadi menunjuk pemilik LAMA yang sudah pindah.
     *
     * @return array<string, true>
     */
    private function getSwapDates($user, string $start, string $end): array
    {
        $swaps = ShiftSwapRequests::whereIn('status', ['in_review', 'approved'])
            ->where(function ($q) use ($user) {
                $q->whereHas('reqSchedule', fn ($q2) => $q2->where('user_id', $user->id))
                    ->orWhereHas('targetSchedule', fn ($q2) => $q2->where('user_id', $user->id));
            })
            ->with(['reqSchedule:id,user_id,date', 'targetSchedule:id,user_id,date'])
            ->get();

        $dates = [];

        foreach ($swaps as $s) {
            foreach ([$s->reqSchedule, $s->targetSchedule] as $slot) {
                if ($slot && $slot->user_id === $user->id) {
                    $d = $slot->date->toDateString();
                    if ($d >= $start && $d <= $end) {
                        $dates[$d] = true;
                    }
                }
            }
        }

        return $dates;
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
        // Cuma tugas yang DITUGASKAN ke user ini (assignee) - bukan yang dia buat/tugaskan ke
        // orang lain. Widget "Tugas Saya" di dashboard itu daftar kerjaan sendiri, bukan daftar
        // penugasan yang dia kasih ke bawahan (itu ada di menu Tugas > Penugasan Individu).
        return Task::where('assigned_to', Auth::id())
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

    /** Board (bukan per tugas) yang punya card belum selesai yang ditugaskan ke user ini, plus
     * jumlahnya - dipakai widget "Tugas Saya" di beranda di bawah daftar tugas personal
     * (instruksi user 2026-09-20). "Belum selesai" = status != COMPLETE; card di List Selesai
     * (is_final) otomatis berstatus COMPLETE, lihat TaskService. Board arsip/terhapus tidak
     * ikut. */
    public function getMyBoardTaskCountsProperty()
    {
        return TaskProject::whereHas('members', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNull('archived_at')
            ->withCount(['tasks as unfinished_count' => fn ($q) => $q
                ->where('assigned_to', Auth::id())
                ->where('status', '!=', TaskStatus::COMPLETE->value)])
            ->get()
            ->filter(fn ($project) => $project->unfinished_count > 0)
            ->sortByDesc('unfinished_count')
            ->take(4)
            ->values();
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
