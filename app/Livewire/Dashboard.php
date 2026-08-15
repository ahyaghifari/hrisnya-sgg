<?php

namespace App\Livewire;

use App\Enum\CustomPermissionEnum;
use App\Enum\LsmCourseStatus;
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

    /** Widget "Jadwal Shift Saya" cuma buat karyawan yang beneran punya jadwal tipe Shift -
     * karyawan Fixed/Flexible tetap punya permission JADWAL_SHIFT_SAYA (gate akses halaman/fitur
     * lain, mis. ajukan tukar shift), tapi widget ini percuma buat mereka (isinya cuma jam kerja
     * tetap yang sudah mereka tahu, bukan roster shift yang berubah-ubah). Dicek dari
     * upcomingShifts yang sudah dihitung buat isi widget-nya sendiri - tidak query ulang. */
    public function getCanJadwalShiftSayaProperty(): bool
    {
        if (! Auth::user()?->can(CustomPermissionEnum::JADWAL_SHIFT_SAYA->value)) {
            return false;
        }

        return collect($this->upcomingShifts)->contains(fn ($day) => $day['schedule']['type'] === 'shift');
    }

    /**
     * 7 hari ke depan (hari ini + 6) - ringkasan kecil di Dashboard, kalender penuh (bisa
     * navigasi bulan lain) ada di halaman /presensi/jadwal-saya (JadwalSayaPage), yang pakai
     * trait ResolvesDayScheduleSummary yang sama supaya hasilnya konsisten.
     */
    public function getUpcomingShiftsProperty(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $start = now()->startOfDay();
        $end   = $start->copy()->addDays(6);

        $holidays = Holidays::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('name', 'date')
            ->mapWithKeys(fn ($name, $date) => [Carbon::parse($date)->toDateString() => $name]);

        $resolver = app(ScheduleResolver::class);
        $days = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dateStr = $cursor->toDateString();

            $days[] = [
                'date' => $cursor->copy(),
                'schedule' => $this->resolveDaySchedule($resolver, $user, $dateStr, $holidays->get($dateStr)),
            ];
        }

        return $days;
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
