<?php

namespace App\Livewire;

use App\Enum\CustomPermissionEnum;
use App\Enum\Task\TaskStatus;
use App\Livewire\Concerns\ResolvesTodayAttendanceStatus;
use App\Models\Task\Task;
use App\Models\LMS\LmsCourseUsers;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Beranda'])]
class Dashboard extends Component
{
    use ResolvesTodayAttendanceStatus;

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

    public function getRecentTasksProperty()
    {
        $userId = Auth::id();

        return Task::where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('assigned_to', $userId);
            })
            ->whereIn('status', [TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value, TaskStatus::REVIEW->value])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();
    }

    // Pelatihan yang sedang dijalani (in_progress) atau sudah jadi member tapi belum mulai
    // (requested/approved) — completed/canceled sengaja dikecualikan, itu bukan "saat ini".
    public function getMyTrainingsProperty()
    {
        return LmsCourseUsers::with('course.category')
            ->where('user_id', Auth::id())
            ->whereIn('status', ['requested', 'approved', 'in_progress'])
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
