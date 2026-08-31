<?php

namespace App\Livewire\Pelatihan;

use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsRespondent;
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

    /**
     * User login adalah pemateri di salah satu pertemuan course ini — dipakai untuk
     * menampilkan tombol buka/kunci kuis & umpan balik langsung di halaman pelatihan,
     * tanpa perlu permission EDIT_DETAIL_KURSUS_LMS (halaman admin). Trainer bisa lewat
     * `trainer_employee_id` (User langsung) atau `trainer->user_id` (row Trainers).
     */
    public function getIsCourseTrainerProperty(): bool
    {
        return $this->course->meetings->contains(
            fn ($meeting) => $meeting->trainer_employee_id === Auth::id()
                || $meeting->trainer?->user_id === Auth::id()
        );
    }

    public function toggleQuizLock(string $meetingQuizId): void
    {
        abort_unless($this->isCourseTrainer, 403);

        $meetingQuiz = LmsMeetingQuizzes::whereHas('meeting', fn ($q) => $q->where('course_id', $this->course->id))
            ->findOrFail($meetingQuizId);

        $meetingQuiz->update(['is_unlocked' => ! $meetingQuiz->is_unlocked]);

        $this->course->load('meetings.quizzes.quiz');
    }

    public function toggleFeedbackLock(): void
    {
        abort_unless($this->isCourseTrainer && $this->course->requires_feedback, 403);

        $this->course->update(['feedback_unlocked' => ! $this->course->feedback_unlocked]);
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
        // Hasil kuis di-key ke respondent_id, bukan user_id — resolve dulu (tanpa membuat baris:
        // pakai value() bukan RespondentService::resolveForEmployee yang firstOrCreate).
        $respondentId = LmsRespondent::where('course_id', $this->course->id)
            ->where('user_id', Auth::id())
            ->where('category', LmsRespondent::CATEGORY_KUIS)
            ->value('id');

        return $respondentId
            && app(QuizRepository::class)->findUserResultByMeetingQuizId($meetingQuizId, $respondentId) !== null;
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
