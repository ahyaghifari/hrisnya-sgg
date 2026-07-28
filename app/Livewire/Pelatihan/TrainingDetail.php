<?php

namespace App\Livewire\Pelatihan;

use App\Models\LMS\LmsCourses;
use App\Repositories\LMS\CourseUserRepository;
use App\Repositories\LMS\QuizRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TrainingDetail extends Component
{
    use WithPagination;

    public LmsCourses $course;

    public ?string $courseUserId = null;
    public ?string $courseUserStatus = null;

    // Pagination member list di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public string $memberSearch = '';

    /** @var array<string, bool> meeting_id => is_attended */
    public array $attendance = [];

    public function mount(LmsCourses $course): void
    {
        $course->load(['category', 'meetings.materials', 'meetings.trainer', 'meetings.quizzes.quiz']);
        $course->loadCount('courseUsers');

        $courseUser = app(CourseUserRepository::class)->getByUserIdAndCourseId(Auth::id(), $course->id);

        $this->course = $course;
        $this->courseUserId = $courseUser?->id;
        $this->courseUserStatus = $courseUser?->status;

        if ($courseUser) {
            $this->attendance = $courseUser->meetingUsers
                ->pluck('is_attended', 'meeting_id')
                ->toArray();
        }
    }

    public function updatedMemberSearch(): void
    {
        $this->resetPage('membersPage');
    }

    public function getIsFollowedProperty(): bool
    {
        return $this->courseUserId !== null;
    }

    // ponytail: sama persis dengan TrainingList::categoryAccent() — hash 4 baris, duplikasi
    // lebih murah daripada trait/service baru untuk 2 pemakaian.
    public function getAccentProperty(): string
    {
        $palette = ['navy', 'green', 'rust', 'teal', 'gold'];
        $categoryName = $this->course->category?->name;

        if (! $categoryName) {
            return $palette[0];
        }

        return $palette[crc32($categoryName) % count($palette)];
    }

    public function getMembersProperty()
    {
        return $this->course->courseUsers()
            ->with('user.profile')
            ->when($this->memberSearch, fn ($q) => $q->whereHas(
                'user',
                fn ($q2) => $q2->where('name', 'like', '%' . $this->memberSearch . '%')
            ))
            ->orderBy('enrolled_at')
            ->paginate(10, ['*'], 'membersPage')
            ->appends([
                'memberSearch' => $this->memberSearch,
            ]);
    }

    // ponytail: sama persis dengan TrainingList::memberAvatars() — pure helper kecil, duplikasi
    // lebih murah daripada abstraksi baru untuk 2 pemakaian.
    public function memberAvatarUrl(?\App\Models\User $user): string
    {
        $picture = $user?->profile?->profile_picture[0] ?? null;

        return $picture
            ? Storage::disk('public')->url($picture)
            : 'https://ui-avatars.com/api/?name=' . urlencode($user?->name ?? 'User') . '&color=7F9CF5&background=EBF4FF';
    }

    public function memberStatusLabel(string $status): string
    {
        return match ($status) {
            'requested' => 'Menunggu',
            'approved' => 'Disetujui',
            'in_progress' => 'Berjalan',
            'completed' => 'Selesai',
            'canceled' => 'Dibatalkan',
            default => ucfirst($status),
        };
    }

    public function memberStatusClass(string $status): string
    {
        return match ($status) {
            'requested' => 'is-pending',
            'canceled' => 'is-canceled',
            default => 'is-approved',
        };
    }

    public function quizAttempted(string $meetingQuizId): bool
    {
        return app(QuizRepository::class)->findUserResultByMeetingQuizId($meetingQuizId, Auth::id()) !== null;
    }

    public function followCourse(): void
    {
        if ($this->isFollowed) {
            return;
        }

        $courseUser = app(CourseUserRepository::class)->assignUserToCourse(Auth::id(), $this->course->id);

        $this->courseUserId = $courseUser->id;
        $this->courseUserStatus = $courseUser->status;
    }

    public function render()
    {
        return view('livewire.pelatihan.training-detail')
            ->layout('components.layout', ['title' => 'Detail Pelatihan']);
    }
}
