<?php

namespace App\Livewire\Pelatihan;

use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Repositories\LMS\CourseUserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Daftar Pelatihan'])]
class TrainingList extends Component
{
    public function getCategoriesProperty()
    {
        return LmsCategories::orderBy('name')->get();
    }

    /**
     * Semua pelatihan published dimuat sekali (tidak dipaginasi server) — cari/filter
     * kategori/status ikut/pagination-nya ditangani List.js di browser (lihat
     * resources/js/app.js `wireDeclarativeFilters()`), tidak roundtrip lagi ke Livewire.
     * courseUserStatus() tiap kartu dibaca sekali di sini lalu ditaruh jadi data-attribute,
     * bukan query ulang per klik filter status.
     */
    protected function baseQuery()
    {
        return LmsCourses::query()
            ->where('status', LsmCourseStatus::Published->value)
            // Jadwal sudah lewat (end_date < hari ini) disembunyikan — belum ada jadwal
            // (end_date null) tetap ditampilkan, bukan "sudah lewat".
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
            ->with(['category', 'meetings.trainer'])
            ->withCount(['courseUsers', 'meetings'])
            ->orderBy('title');
    }

    // Avatar stack "+N Members" — replikasi ringkas dari CoursesResource::toArray() (API mobile).
    public function memberAvatars(LmsCourses $course): array
    {
        return $course->courseUsers()
            ->with('user.profile')
            ->take(3)
            ->get()
            ->map(function ($courseUser) {
                $user = $courseUser->user;
                $picture = $user?->profilePhotoPath();

                return $picture
                    ? Storage::disk('public')->url($picture)
                    : 'https://ui-avatars.com/api/?name=' . urlencode($user?->name ?? 'User') . '&color=7F9CF5&background=EBF4FF';
            })
            ->toArray();
    }

    // null = belum ikut, 'requested' = menunggu approval HC/admin, selain itu = sudah disetujui.
    public function courseUserStatus(LmsCourses $course): ?string
    {
        return app(CourseUserRepository::class)->getByUserIdAndCourseId(Auth::id(), $course->id)?->status;
    }

    // Sama dengan TrainingDetail::isCourseTrainer; meetings.trainer sudah di-eager-load di baseQuery().
    public function isTrainer(LmsCourses $course): bool
    {
        return $course->meetings->contains(
            fn ($meeting) => $meeting->trainer_employee_id === Auth::id()
                || $meeting->trainer?->user_id === Auth::id()
        );
    }

    // Warna aksen per kategori — hash deterministik ke 5 warna brand yang sudah ada
    // (palet sama dengan .section-nav-btn), jadi kode warna konsisten tiap kali dilihat
    // tanpa perlu kolom warna baru di lms_categories.
    public function categoryAccent(?string $categoryName): string
    {
        $palette = ['navy', 'green', 'rust', 'teal', 'gold'];

        if (! $categoryName) {
            return $palette[0];
        }

        return $palette[crc32($categoryName) % count($palette)];
    }

    public function getCoursesProperty()
    {
        return $this->baseQuery()->get();
    }

    public function render()
    {
        return view('livewire.pelatihan.training-list');
    }
}
