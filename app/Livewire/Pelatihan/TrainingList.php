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
    public ?string $categoryId = null;

    public string $search = '';

    // all | followed | not_followed
    public string $followStatus = 'all';

    public function getCategoriesProperty()
    {
        return LmsCategories::orderBy('name')->get();
    }

    // Sumber data untuk grid card (mobile: 1 kolom, desktop: 4-5 kolom — lihat training-list.blade.php).
    protected function baseQuery()
    {
        $userId = Auth::id();

        return LmsCourses::query()
            ->where('status', LsmCourseStatus::Published->value)
            ->with('category')
            ->withCount(['courseUsers', 'meetings'])
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('title', 'like', '%' . $this->search . '%')
                ->orWhere('code', 'like', '%' . $this->search . '%')))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->followStatus === 'followed', fn ($q) => $q->whereHas(
                'courseUsers',
                fn ($q2) => $q2->where('user_id', $userId)
            ))
            ->when($this->followStatus === 'not_followed', fn ($q) => $q->whereDoesntHave(
                'courseUsers',
                fn ($q2) => $q2->where('user_id', $userId)
            ))
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
                $picture = $user?->profile?->profile_picture[0] ?? null;

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
        return $this->baseQuery()->paginate(12);
    }

    public function render()
    {
        return view('livewire.pelatihan.training-list');
    }
}
