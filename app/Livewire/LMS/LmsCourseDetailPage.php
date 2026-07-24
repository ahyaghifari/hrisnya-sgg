<?php

namespace App\Livewire\LMS;

use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMaterials;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Halaman detail Pelatihan portal — tujuan redirect setelah Tambah Pelatihan (CreateLmsCourse).
 * 3 tab (Peserta/Pertemuan/Umpan Balik), pola tab sama seperti ScheduleDetailPage (Lihat Jadwal
 * absensi). Peserta py CRUD ringkas (tambah/approve/batalkan/hapus, direplikasi dari
 * `LmsCoursesForm::participantsSchema()` admin).
 *
 * Pertemuan REDESIGN dari admin (bukan port) — disederhanakan atas permintaan user:
 * - Tanpa tab "Penilaian Trainer" sama sekali (opsional, pelatihan bisa berjalan tanpa itu).
 * - Tanpa filter Kategori di tab Quiz — kategori kuis otomatis ikut kategori course.
 * - Tipe Absensi dipaksa 'offline', tidak ada field-nya.
 * - Tanggal Mulai/Selesai per pertemuan disembunyikan kalau course cuma 1 hari
 *   (`isSingleDayCourse`) — otomatis disamakan dengan tanggal course.
 * - Tombol "Tambah Pertemuan" cuma muncul kalau course lebih dari 1 hari (pelatihan 1 hari
 *   memang cuma butuh 1 pertemuan, yang sudah otomatis dibuat CreateLmsCourse).
 * - Tiap pertemuan ditampilkan sebagai accordion collapse, expand memuat form edit Detail
 *   Pertemuan + daftar Materi + daftar Quiz sekaligus (bukan sub-tab lagi di dalam
 *   accordion, biar tidak 2 lapis tab).
 */
#[Layout('components.layout', ['title' => 'Detail Pelatihan'])]
class LmsCourseDetailPage extends Component
{
    use WithFileUploads;

    public LmsCourses $course;

    public string $tab = 'peserta';

    public bool $showAddParticipantForm = false;
    public array $selectedUserIds = [];

    public ?string $expandedMeetingId = null;
    public ?string $editingMeetingId = null;
    public bool $showAddMeetingForm = false;
    public string $meetingTitle = '';
    public string $meetingDescription = '';
    public ?string $meetingTrainerId = null;
    public ?string $meetingStartDate = null;
    public ?string $meetingEndDate = null;

    public ?string $addingMaterialForMeetingId = null;
    public string $materialTitle = '';
    public $materialFile = null;

    public ?string $addingQuizForMeetingId = null;
    public ?string $quizId = null;
    public ?int $quizDuration = null;

    public function mount(LmsCourses $course): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
        abort_unless(
            PermissionHelper::hasGlobalRole(Auth::user()) || $course->company_id === Auth::user()->company_id,
            403
        );

        $this->course = $course->load(['category', 'competencies']);

        // Course 1 hari cuma pernah punya 1 pertemuan (tombol "Tambah Pertemuan" disembunyikan)
        // — langsung preload form Detail Pertemuan-nya, tidak perlu klik accordion buat expand.
        if ($this->isSingleDayCourse) {
            $meeting = $this->course->meetings()->first();

            if ($meeting) {
                $this->expandedMeetingId = $meeting->id;
                $this->editingMeetingId = $meeting->id;
                $this->meetingTitle = $meeting->title;
                $this->meetingDescription = $meeting->description ?? '';
                $this->meetingTrainerId = $meeting->trainer_id;
            }
        }
    }

    public function getCanEditDetailProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_DETAIL_KURSUS_LMS->value);
    }

    private function assertCanEdit(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_DETAIL_KURSUS_LMS->value), 403);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function getIsSingleDayCourseProperty(): bool
    {
        return $this->course->start_date && $this->course->start_date === $this->course->end_date;
    }

    public function getParticipantsProperty()
    {
        return LmsCourseUsers::where('course_id', $this->course->id)
            ->with('user')
            ->get();
    }

    public function getAvailableUserOptionsProperty()
    {
        $enrolledIds = $this->participants->pluck('user_id');

        return User::where('company_id', $this->course->company_id)
            ->whereNotIn('id', $enrolledIds)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function openAddParticipantForm(): void
    {
        $this->selectedUserIds = [];
        $this->showAddParticipantForm = true;
    }

    public function closeAddParticipantForm(): void
    {
        $this->showAddParticipantForm = false;
    }

    public function addParticipants(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'selectedUserIds' => ['required', 'array', 'min:1'],
            'selectedUserIds.*' => ['exists:users,id'],
        ]);

        foreach ($this->selectedUserIds as $userId) {
            LmsCourseUsers::create([
                'course_id' => $this->course->id,
                'user_id' => $userId,
                'is_mandatory' => true,
                'status' => CourseUserStatus::APPROVED->value,
                'score' => 0,
            ]);
        }

        $this->selectedUserIds = [];
        $this->showAddParticipantForm = false;

        Notification::make()->title('Peserta berhasil ditambahkan')->success()->send();
    }

    public function approveParticipant(string $id): void
    {
        $this->assertCanEdit();

        $participant = LmsCourseUsers::where('course_id', $this->course->id)->findOrFail($id);
        $participant->update(['status' => CourseUserStatus::APPROVED->value, 'enrolled_at' => now()]);

        Notification::make()->title('Peserta disetujui')->success()->send();
    }

    public function cancelParticipant(string $id): void
    {
        $this->assertCanEdit();

        $participant = LmsCourseUsers::where('course_id', $this->course->id)->findOrFail($id);
        $participant->update(['status' => CourseUserStatus::CANCELED->value]);

        Notification::make()->title('Keikutsertaan peserta dibatalkan')->success()->send();
    }

    public function removeParticipant(string $id): void
    {
        $this->assertCanEdit();

        LmsCourseUsers::where('course_id', $this->course->id)->findOrFail($id)->delete();

        Notification::make()->title('Peserta dihapus dari pelatihan')->success()->send();
    }

    public function getMeetingsProperty()
    {
        return $this->course->meetings()->with(['materials', 'quizzes.quiz', 'trainer'])->orderBy('start_date')->get();
    }

    public function getTrainerOptionsProperty()
    {
        return Trainers::orderBy('name')->pluck('name', 'id');
    }

    private function resetMeetingForm(): void
    {
        $this->meetingTitle = '';
        $this->meetingDescription = '';
        $this->meetingTrainerId = null;
        $this->meetingStartDate = null;
        $this->meetingEndDate = null;
        $this->resetErrorBag();
    }

    public function openAddMeetingForm(): void
    {
        if ($this->isSingleDayCourse) {
            return;
        }

        $this->expandedMeetingId = null;
        $this->editingMeetingId = null;
        $this->resetMeetingForm();
        $this->showAddMeetingForm = true;
    }

    public function closeAddMeetingForm(): void
    {
        $this->showAddMeetingForm = false;
        $this->resetMeetingForm();
    }

    public function toggleMeetingAccordion(string $id): void
    {
        $this->showAddMeetingForm = false;
        $this->addingMaterialForMeetingId = null;
        $this->addingQuizForMeetingId = null;

        if ($this->expandedMeetingId === $id) {
            $this->expandedMeetingId = null;
            $this->editingMeetingId = null;

            return;
        }

        $meeting = $this->course->meetings()->findOrFail($id);

        $this->expandedMeetingId = $id;
        $this->editingMeetingId = $id;
        $this->meetingTitle = $meeting->title;
        $this->meetingDescription = $meeting->description ?? '';
        $this->meetingTrainerId = $meeting->trainer_id;
        $this->meetingStartDate = $meeting->start_date;
        $this->meetingEndDate = $meeting->end_date;
        $this->resetErrorBag();
    }

    protected function meetingRules(): array
    {
        $rules = [
            'meetingDescription' => ['nullable', 'string'],
            'meetingTrainerId' => ['required', 'exists:trainers,id'],
        ];

        if ($this->isSingleDayCourse) {
            // Cuma 1 pertemuan, judulnya otomatis ikut judul pelatihan — tidak ada field/input.
            return $rules;
        }

        $rules['meetingTitle'] = ['required', 'string', 'max:255'];
        $rules['meetingStartDate'] = ['required', 'date'];
        $rules['meetingEndDate'] = ['required', 'date', 'after_or_equal:meetingStartDate'];

        return $rules;
    }

    public function saveMeeting(): void
    {
        $this->assertCanEdit();

        $validated = $this->validate($this->meetingRules());

        $data = [
            'title' => $this->isSingleDayCourse ? $this->course->title : $validated['meetingTitle'],
            'description' => $validated['meetingDescription'] ?: null,
            'trainer_id' => $validated['meetingTrainerId'],
            'attendance_type' => 'offline',
            'start_date' => $this->isSingleDayCourse ? $this->course->start_date : $validated['meetingStartDate'],
            'end_date' => $this->isSingleDayCourse ? $this->course->end_date : $validated['meetingEndDate'],
        ];

        if ($this->editingMeetingId) {
            $this->course->meetings()->findOrFail($this->editingMeetingId)->update($data);
        } else {
            $this->course->meetings()->create($data);
        }

        // LmsMeetings::booted() 'saved' resinkronin start_date/end_date course dari semua
        // meeting-nya — refresh supaya header "Tanggal Pelaksanaan" langsung ikut update.
        $this->course->refresh();

        $this->showAddMeetingForm = false;
        $this->expandedMeetingId = null;
        $this->editingMeetingId = null;
        $this->resetMeetingForm();

        Notification::make()->title('Pertemuan berhasil disimpan')->success()->send();
    }

    public function openAddMaterialForm(string $meetingId): void
    {
        $this->addingMaterialForMeetingId = $meetingId;
        $this->materialTitle = '';
        $this->materialFile = null;
        $this->resetErrorBag();
    }

    public function closeAddMaterialForm(): void
    {
        $this->addingMaterialForMeetingId = null;
    }

    public function saveMaterial(): void
    {
        $this->assertCanEdit();

        $validated = $this->validate([
            'materialTitle' => ['required', 'string', 'max:255'],
            'materialFile' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx'],
        ]);

        $meeting = $this->course->meetings()->findOrFail($this->addingMaterialForMeetingId);
        $path = $this->materialFile->store('lms/materials', 'public');

        $meeting->materials()->create([
            'title' => $validated['materialTitle'],
            'file_path' => $path,
            'file_type' => $this->materialFile->getClientMimeType(),
            'file_name' => $this->materialFile->getClientOriginalName(),
        ]);

        $this->addingMaterialForMeetingId = null;

        Notification::make()->title('Materi berhasil ditambahkan')->success()->send();
    }

    public function removeMaterial(string $id): void
    {
        $this->assertCanEdit();

        $material = LmsMaterials::whereHas('meeting', fn ($q) => $q->where('course_id', $this->course->id))->findOrFail($id);

        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        Notification::make()->title('Materi dihapus')->success()->send();
    }

    public function openAddQuizForm(string $meetingId): void
    {
        $this->addingQuizForMeetingId = $meetingId;
        $this->quizId = null;
        $this->quizDuration = null;
        $this->resetErrorBag();
    }

    public function closeAddQuizForm(): void
    {
        $this->addingQuizForMeetingId = null;
    }

    /** Filter kategori dihapus — otomatis ikut kategori course, kuis yang sudah dipasang di
     * pertemuan ini juga dikecualikan (sama seperti logic admin `LmsCoursesForm`). */
    public function getQuizOptionsProperty()
    {
        if (! $this->addingQuizForMeetingId) {
            return collect();
        }

        $usedQuizIds = LmsMeetingQuizzes::where('meeting_id', $this->addingQuizForMeetingId)->pluck('quiz_id');

        return LmsQuizzes::where('category_id', $this->course->category_id)
            ->whereNotIn('id', $usedQuizIds)
            ->orderBy('title')
            ->pluck('title', 'id');
    }

    public function saveQuiz(): void
    {
        $this->assertCanEdit();

        $validated = $this->validate([
            'quizId' => ['required', 'exists:lms_quizzes,id'],
            'quizDuration' => ['required', 'integer', 'min:1'],
        ]);

        $meeting = $this->course->meetings()->findOrFail($this->addingQuizForMeetingId);

        $meeting->quizzes()->create([
            'quiz_id' => $validated['quizId'],
            'duration' => $validated['quizDuration'],
        ]);

        $this->addingQuizForMeetingId = null;

        Notification::make()->title('Quiz berhasil ditambahkan')->success()->send();
    }

    public function removeQuiz(string $id): void
    {
        $this->assertCanEdit();

        LmsMeetingQuizzes::whereHas('meeting', fn ($q) => $q->where('course_id', $this->course->id))->findOrFail($id)->delete();

        Notification::make()->title('Quiz dihapus')->success()->send();
    }

    public function getFeedbacksProperty()
    {
        return $this->course->feedbacks()->with('user')->latest()->get();
    }

    public function getAverageRatingProperty(): float
    {
        return round($this->feedbacks->avg('rating') ?? 0, 2);
    }

    public function render()
    {
        return view('livewire.lms.lms-course-detail-page');
    }
}
