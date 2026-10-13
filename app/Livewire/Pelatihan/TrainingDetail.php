<?php

namespace App\Livewire\Pelatihan;

use App\Models\LMS\LmsCourseAbsence;
use App\Models\LMS\LmsCourseFeedbacks;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\LMS\CourseAbsenceService;
use Filament\Notifications\Notification;
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

    public function getHasSubmittedFeedbackProperty(): bool
    {
        return LmsCourseFeedbacks::where('course_id', $this->course->id)
            ->whereHas('respondent', fn ($q) => $q->where('user_id', Auth::id())
                ->where('category', LmsRespondent::CATEGORY_UMPAN_BALIK))
            ->exists();
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
                fn ($q2) => $q2->searchName($this->memberSearch)
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
        $picture = $user?->profilePhotoPath();

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

    public bool $showIzin = false;
    public ?string $izinMeetingId = null;
    public ?string $izinReason = null;
    public ?string $izinNote = null;

    /** Pengajuan izin user ini untuk course ini (terbaru dulu). */
    public function getIzinRequestsProperty()
    {
        return LmsCourseAbsence::query()
            ->where('course_id', $this->course->id)
            ->where('user_id', Auth::id())
            ->with('meeting')
            ->latest()
            ->get();
    }

    /** Pertemuan yang belum lewat dan belum punya izin aktif — pilihan untuk izin tidak hadir. */
    public function getIzinMeetingsProperty()
    {
        // Dibatalkan juga dihitung: sekali batal, pertemuan itu tidak bisa diajukan izin lagi.
        // Ditolak boleh diajukan ulang.
        $taken = $this->izinRequests->whereIn('status', ['in_review', 'approved', 'cancelled'])->pluck('meeting_id');

        return $this->course->meetings
            ->reject(fn ($m) => $taken->contains($m->id))
            ->filter(fn ($m) => ($m->start_date ?? $m->meeting_date)
                && \Carbon\Carbon::parse($m->start_date ?? $m->meeting_date)->toDateString() >= now()->toDateString())
            ->sortBy('start_date');
    }

    /** Izin peserta lain di course ini yang sedang menunggu giliran persetujuan user login. */
    public function getIzinApprovalsProperty()
    {
        return LmsCourseAbsence::query()
            ->where('course_id', $this->course->id)
            ->where('status', 'in_review')
            ->where('user_id', '!=', Auth::id())
            ->with(['user', 'meeting', 'approvalSnapshot'])
            ->get()
            ->filter(fn ($a) => $a->approvalSnapshot
                && app(ApprovalPermissionService::class)->canApprove($a->approvalSnapshot->id, Auth::user()));
    }

    /** Rantai persetujuan satu izin (Diajukan -> tiap approver), format ApprovalTimelineService. */
    public function izinTimeline(LmsCourseAbsence $absence): array
    {
        return app(\App\Services\Approval\ApprovalTimelineService::class)->build($absence);
    }

    /** Peserta membatalkan izinnya sendiri selagi masih diproses. */
    public function cancelIzin(string $absenceId): void
    {
        $absence = $this->izinRequests->firstWhere('id', $absenceId);
        abort_unless($absence && $absence->canBeCancelled(), 403);

        if ($absence->status === 'in_review') {
            $this->advanceIzin($absence, 'cancelled', 'Dibatalkan oleh peserta', 'Pengajuan izin dibatalkan');

            return;
        }

        // Sudah disetujui final: batal langsung (tidak ada efek samping yang perlu dibalik),
        // dicatat di riwayat approval & approver yang menyetujui diberi tahu.
        $approverId = $absence->approved_by;
        $absence->update(['status' => 'cancelled', 'cancelled_by' => Auth::id(), 'cancelled_at' => now()]);

        app(\App\Repositories\Approval\ApprovalHistoryRepository::class)->create([
            'requestable_type' => \App\Enum\Approval\ApprovalModuleEnum::TRAINING_ABSENCE->value,
            'requestable_id' => $absence->id,
            'snapshot_id' => $absence->approvalSnapshot?->id,
            'action' => 'cancelled',
            'actor_id' => Auth::id(),
            'actor_name' => Auth::user()->name,
            'note' => 'Dibatalkan oleh peserta setelah disetujui',
        ]);

        \App\Models\User::find($approverId)?->notify(new \App\Notifications\RequestStatusChanged(
            \App\Enum\Approval\ApprovalModuleEnum::TRAINING_ABSENCE->value, $absence->id, 'cancelled', Auth::user()->name
        ));

        Notification::make()->title('Izin dibatalkan')->success()->send();
    }

    public function approveIzin(string $absenceId): void
    {
        $this->advanceIzin($this->izinApprovals->firstWhere('id', $absenceId), 'approved', $this->izinNote ?: 'Disetujui', 'Izin disetujui');
    }

    public function rejectIzin(string $absenceId): void
    {
        $this->advanceIzin($this->izinApprovals->firstWhere('id', $absenceId), 'rejected', $this->izinNote ?: 'Ditolak', 'Izin ditolak');
    }

    private function advanceIzin(?LmsCourseAbsence $absence, string $action, string $note, string $successMessage): void
    {
        abort_unless($absence, 403);

        try {
            app(ApprovalFlowService::class)->advanceStep($absence->approvalSnapshot, Auth::id(), $note, $action);
        } catch (\Exception $e) {
            Notification::make()->title('Gagal: ' . $e->getMessage())->danger()->send();

            return;
        }

        $this->izinNote = null;
        Notification::make()->title($successMessage)->success()->send();
    }

    /** Alur persetujuan = alur izin Tidak Masuk berdasarkan jabatan (lihat CourseAbsenceService). */
    public function submitIzin(): void
    {
        abort_unless($this->isFollowed && in_array($this->courseUserStatus, ['approved', 'in_progress'], true), 403);

        // Pilihan tinggal satu pertemuan: tidak ada yang perlu dipilih peserta.
        if ($this->izinMeetings->count() === 1) {
            $this->izinMeetingId = $this->izinMeetings->first()->id;
        }

        $this->validate([
            'izinMeetingId' => ['required', \Illuminate\Validation\Rule::in($this->izinMeetings->pluck('id')->all())],
            'izinReason' => ['required', 'string', 'max:1000'],
        ], [
            'izinMeetingId.required' => 'Pilih pertemuan.',
            'izinMeetingId.in' => 'Pertemuan tidak valid.',
            'izinReason.required' => 'Alasan wajib diisi.',
        ]);

        try {
            app(CourseAbsenceService::class)->store(
                $this->course->meetings->firstWhere('id', $this->izinMeetingId),
                Auth::user(),
                $this->izinReason,
            );
        } catch (\Exception $e) {
            $this->addError('izinReason', 'Gagal mengajukan: ' . $e->getMessage());

            return;
        }

        $this->reset('showIzin', 'izinMeetingId', 'izinReason');
        Notification::make()->title('Pengajuan izin berhasil dikirim')->success()->send();
    }

    public function followCourse(): void
    {
        if ($this->isFollowed || $this->isCourseTrainer) {
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
