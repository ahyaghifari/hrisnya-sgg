<?php

namespace App\Livewire\LMS;

use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\LMS\TrainingCategoryEnum;
use App\Enum\LsmCourseStatus;
use App\Exports\LMS\MeetingAttendanceExport;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseMeetingUsers;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMaterials;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizResults;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\Trainer\TrainerAssessmentResults;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use App\Services\LMS\CourseUserService;
use App\Services\LMS\MeetingHonorariumService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

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
    public string $participantSearch = '';

    public ?string $expandedMeetingId = null;
    public ?string $editingMeetingId = null;
    public bool $showAddMeetingForm = false;
    public string $meetingTitle = '';
    public string $meetingDescription = '';
    public ?string $meetingTrainerId = null;
    public ?string $meetingStartDate = null;
    public ?string $meetingEndDate = null;
    public ?string $meetingStartTime = null;
    public ?string $meetingEndTime = null;

    // Modal "Tambah Pemateri" — quick-create langsung dari tab Pertemuan, field & validasi
    // sama persis TrainersPage (/lms/pemateri), tanpa perlu pindah halaman.
    public bool $showCreateTrainerModal = false;
    public string $trainerName = '';
    public string $trainerType = 'external';
    public ?string $trainerUserId = null;
    public string $trainerExpertise = '';
    public string $trainerEmail = '';
    public string $trainerPhone = '';
    public string $trainerCompany = '';
    public string $trainerAddress = '';

    // Mode Absensi — checklist manual Absen Masuk/Pulang + Keterangan per peserta, per
    // pertemuan (beda dari clock-in mobile otomatis, lihat migration kolomnya). Cuma 1 pertemuan
    // bisa dalam mode ini di satu waktu (sama seperti accordion, cukup 1 state di-share).
    public ?string $attendanceMeetingId = null;
    public array $attendanceForm = []; // user_id => ['masuk'=>bool,'pulang'=>bool,'keterangan'=>string]
    public string $attendanceSearch = '';
    public ?string $attendanceViewMeetingId = null; // tombol "Lihat" — read-only, beda dari attendanceMeetingId (mode isi/edit)

    public ?string $addingMaterialForMeetingId = null;
    public string $materialTitle = '';
    public $materialFile = null;

    public ?string $addingQuizForMeetingId = null;
    public ?string $quizId = null;
    public ?int $quizDuration = null;
    public bool $quizIsGraduationRequirement = false;

    // Tab "Penilaian dan Gaji Pemateri" — pola picker sama seperti Pertemuan: course 1 hari
    // langsung preload satu-satunya pertemuan, course multi-pertemuan pilih dulu.
    public ?string $selectedHonorMeetingId = null;
    public array $honorCategorySelections = []; // variable_id => category_id (jalur scored)
    public string $honorFixedAmount = ''; // jalur fixed (Umum/Sertifikasi Dokter Umum)

    // Modal "Lihat QR" kuis/umpan balik publik — token dibuat otomatis saat publish (§4),
    // modal ini cuma menampilkan QR + link mentah untuk disalin.
    public ?string $qrModalUrl = null;
    public ?string $qrModalTitle = null;
    public ?string $qrModalLink = null;

    // Modal hasil kuis (tab Pertemuan) — 1 modal dipakai 2 mode: 'summary' (rangkuman angka)
    // & 'list' (tabel semua yang mengerjakan, karyawan maupun respondent publik).
    public ?string $quizResultsMeetingQuizId = null;
    public ?string $quizResultsMode = null;

    public function mount(LmsCourses $course): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
        abort_unless(
            PermissionHelper::hasGlobalRole(Auth::user()) || $course->company_id === Auth::user()->company_id,
            403
        );

        $this->course = $course->load(['category', 'competencies']);

        // Self-healing: kalau course sudah Published tapi token QR belum ada (mis. requires_feedback
        // baru diaktifkan belakangan, atau kuis baru ditambahkan setelah publish), lengkapi di sini
        // — bukan cuma sekali saat transisi Draft->Published, karena tombol Publish sudah hilang
        // begitu Published, jadi tidak ada jalur lain untuk generate token buat course lama.
        if ($this->course->status === LsmCourseStatus::Published->value) {
            $this->generatePublicTokens();
            $this->course->refresh();
        }

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
                $this->meetingStartTime = $meeting->start_time ? substr($meeting->start_time, 0, 5) : null;
                $this->meetingEndTime = $meeting->end_time ? substr($meeting->end_time, 0, 5) : null;

                $this->selectHonorMeeting($meeting->id);
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

    /** Approve/tolak pendaftaran peserta - permission approval sendiri (sama dipakai
     * PendingApprovalsCard di portal), bukan cuma yang boleh edit detail pelatihan - HC yang
     * cuma pegang izin approve tetap bisa approve dari halaman ini. */
    public function getCanApproveEnrollmentProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::APPROVE_PENGAJUAN_PELATIHAN->value);
    }

    private function assertCanApproveEnrollment(): void
    {
        abort_unless($this->canEditDetail || $this->canApproveEnrollment, 403);
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'umpan-balik' && ! $this->course->requires_feedback) {
            return;
        }

        $this->tab = $tab;
    }

    public function getIsSingleDayCourseProperty(): bool
    {
        return $this->course->start_date && $this->course->start_date === $this->course->end_date;
    }

    public function getParticipantsProperty()
    {
        $search = trim($this->participantSearch);

        return LmsCourseUsers::where('course_id', $this->course->id)
            ->with('user')
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")))
            ->get();
    }

    /** Sengaja tidak ikut $participantSearch — dipakai Mode Absensi supaya daftar peserta yang
     * ditandai tidak diam-diam berkurang gara-gara filter pencarian tab Peserta masih kepakai. */
    public function getAllParticipantsProperty()
    {
        return LmsCourseUsers::where('course_id', $this->course->id)->with('user')->get();
    }

    /** Cuma filter tampilan baris di Mode Absensi — $attendanceForm tetap mencakup semua
     * peserta (dibangun sekali saat openAttendanceMode), jadi ganti kata kunci cari tidak
     * membuang centang yang sudah ditandai pada peserta yang sedang tersembunyi. */
    public function getAttendanceParticipantsProperty()
    {
        $search = trim($this->attendanceSearch);

        if ($search === '') {
            return $this->allParticipants;
        }

        return $this->allParticipants->filter(
            fn ($participant) => str_contains(strtolower((string) $participant->user?->name), strtolower($search))
        );
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
                'enrolled_at' => now(),
            ]);
        }

        $this->selectedUserIds = [];
        $this->showAddParticipantForm = false;

        Notification::make()->title('Peserta berhasil ditambahkan')->success()->send();
    }

    public function approveParticipant(string $id): void
    {
        $this->assertCanApproveEnrollment();

        $participant = LmsCourseUsers::where('course_id', $this->course->id)->findOrFail($id);
        app(CourseUserService::class)->approveEnrollment($participant, Auth::user());

        Notification::make()->title('Peserta disetujui')->success()->send();
    }

    public function cancelParticipant(string $id): void
    {
        $this->assertCanApproveEnrollment();

        $participant = LmsCourseUsers::where('course_id', $this->course->id)->findOrFail($id);
        app(CourseUserService::class)->rejectEnrollment($participant, Auth::user());

        Notification::make()->title('Keikutsertaan peserta dibatalkan')->success()->send();
    }

    public function removeParticipant(string $id): void
    {
        $this->assertCanEdit();

        abort_if($this->course->status === LsmCourseStatus::Published->value, 403);

        LmsCourseUsers::where('course_id', $this->course->id)->findOrFail($id)->delete();

        Notification::make()->title('Peserta dihapus dari pelatihan')->success()->send();
    }

    public function getMeetingsProperty()
    {
        return $this->course->meetings()->with(['materials', 'quizzes.quiz', 'trainer'])->orderBy('start_date')->get();
    }

    public function getTotalJpProperty(): int
    {
        return (int) $this->meetings->sum('jp');
    }

    public function getTotalDurationMinutesProperty(): int
    {
        return $this->meetings->sum(function ($meeting) {
            if (! $meeting->start_time || ! $meeting->end_time) {
                return 0;
            }

            return \Carbon\Carbon::parse($meeting->start_time)->diffInMinutes(\Carbon\Carbon::parse($meeting->end_time));
        });
    }

    /**
     * Syarat publish: minimal 1 peserta, dan tiap pertemuan wajib sudah punya pemateri +
     * jam mulai/selesai. Dipakai buat checklist di header (tombol Publish baru muncul kalau
     * semua item ini lolos) — dicek ulang juga di publishCourse() supaya tidak bisa dilewati
     * lewat cara lain (mis. request langsung).
     */
    public function getPublishChecklistProperty(): array
    {
        return [
            'hasParticipant' => LmsCourseUsers::where('course_id', $this->course->id)->exists(),
            'meetingsComplete' => $this->meetings->isNotEmpty() && $this->meetings->every(
                fn ($meeting) => $meeting->trainer_id && $meeting->start_time && $meeting->end_time
            ),
        ];
    }

    public function getCanPublishProperty(): bool
    {
        return ! in_array(false, $this->publishChecklist, true);
    }

    public function publishCourse(): void
    {
        $this->assertCanEdit();

        abort_unless($this->canPublish, 403);

        $this->course->update(['status' => LsmCourseStatus::Published->value]);
        $this->course->refresh();

        $this->generatePublicTokens();

        Notification::make()->title('Pelatihan berhasil dipublish')->success()->send();
    }

    /**
     * Token QR kuis/umpan balik publik — tidak ada tombol "Publish Kuis"/"Publish Umpan Balik"
     * terpisah, lihat planning §4. Dipanggil dari publishCourse() (saat transisi Draft->Published)
     * DAN dari mount() (self-healing untuk course yang sudah Published lebih dulu, lihat
     * komentar di mount()). Idempotent: cuma mengisi yang masih kosong, tidak pernah menimpa
     * token yang sudah ada.
     */
    private function generatePublicTokens(): void
    {
        LmsMeetingQuizzes::whereHas('meeting', fn ($q) => $q->where('course_id', $this->course->id))
            ->whereNull('public_token')
            ->get()
            ->each(fn ($meetingQuiz) => $meetingQuiz->update(['public_token' => \Illuminate\Support\Str::random(40)]));

        if ($this->course->requires_feedback && ! $this->course->feedback_public_token) {
            $this->course->update(['feedback_public_token' => \Illuminate\Support\Str::random(40)]);
        }
    }

    public function showQuizQr(string $meetingQuizId): void
    {
        $meetingQuiz = LmsMeetingQuizzes::findOrFail($meetingQuizId);
        abort_if(! $meetingQuiz->public_token, 404);

        $this->qrModalTitle = 'QR Kuis: ' . $meetingQuiz->quiz->title;
        $this->qrModalLink = route('lms.public.quiz', ['token' => $meetingQuiz->public_token]);
        $this->qrModalUrl = app(\App\Services\LMS\QrCodeService::class)->getQuizQrUrl($meetingQuiz->id, $meetingQuiz->public_token);
    }

    public function showFeedbackQr(): void
    {
        abort_if(! $this->course->feedback_public_token, 404);

        $this->qrModalTitle = 'QR Umpan Balik: ' . $this->course->title;
        $this->qrModalLink = route('lms.public.feedback', ['token' => $this->course->feedback_public_token]);
        $this->qrModalUrl = app(\App\Services\LMS\QrCodeService::class)->getFeedbackQrUrl($this->course->id, $this->course->feedback_public_token);
    }

    public function closeQrModal(): void
    {
        $this->qrModalUrl = null;
        $this->qrModalTitle = null;
        $this->qrModalLink = null;
    }

    public function showQuizResultsSummary(string $meetingQuizId): void
    {
        $this->quizResultsMeetingQuizId = $meetingQuizId;
        $this->quizResultsMode = 'summary';
    }

    public function showQuizResultsList(string $meetingQuizId): void
    {
        $this->quizResultsMeetingQuizId = $meetingQuizId;
        $this->quizResultsMode = 'list';
    }

    public function closeQuizResultsModal(): void
    {
        $this->quizResultsMeetingQuizId = null;
        $this->quizResultsMode = null;
    }

    public function getQuizResultsProperty()
    {
        if (! $this->quizResultsMeetingQuizId) {
            return collect();
        }

        return LmsQuizResults::where('meeting_quiz_id', $this->quizResultsMeetingQuizId)
            ->with('respondent')
            ->latest('completed_at')
            ->get();
    }

    public function getQuizResultsSummaryProperty(): array
    {
        $results = $this->quizResults;

        return [
            'total' => $results->count(),
            'employee_count' => $results->filter(fn ($r) => $r->respondent?->user_id)->count(),
            'public_count' => $results->filter(fn ($r) => ! $r->respondent?->user_id)->count(),
            'avg_score' => $results->isEmpty() ? 0 : round($results->avg('total_score'), 1),
            'max_score' => $results->max('total_score') ?? 0,
            'min_score' => $results->min('total_score') ?? 0,
            'pending_review' => $results->where('status', 'pending_review')->count(),
        ];
    }

    public function getTrainerOptionsProperty()
    {
        $options = Trainers::where('is_active', true)->orderBy('name')->pluck('name', 'id');

        // Kalau pertemuan yang sedang diedit pemateri-nya sudah dinonaktifkan, tetap tampilkan
        // di daftar (supaya pilihan yang tersimpan tidak hilang begitu saja dari dropdown) —
        // tidak dipaksa ganti, admin baru ganti kalau memang mau.
        if ($this->meetingTrainerId && ! $options->has($this->meetingTrainerId)) {
            $inactiveTrainer = Trainers::find($this->meetingTrainerId);
            if ($inactiveTrainer) {
                $options->put($inactiveTrainer->id, $inactiveTrainer->name . ' (nonaktif)');
            }
        }

        return $options;
    }

    public function getEmployeeOptionsProperty()
    {
        return User::where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function getCanCreateTrainerProperty(): bool
    {
        return Auth::user()->can('create', Trainers::class);
    }

    public function openCreateTrainerModal(): void
    {
        abort_unless(Auth::user()->can('create', Trainers::class), 403);

        $this->resetTrainerForm();
        $this->showCreateTrainerModal = true;
    }

    public function closeCreateTrainerModal(): void
    {
        $this->showCreateTrainerModal = false;
    }

    public function saveTrainer(): void
    {
        abort_unless(Auth::user()->can('create', Trainers::class), 403);

        $validated = $this->validate([
            'trainerName' => 'required|string|max:255',
            'trainerType' => 'required|in:internal,external',
            'trainerUserId' => 'nullable|exists:users,id',
            'trainerExpertise' => 'nullable|string|max:255',
            'trainerEmail' => 'nullable|email|max:255',
            'trainerPhone' => 'nullable|string|max:255',
            'trainerCompany' => 'nullable|string|max:255',
            'trainerAddress' => 'nullable|string',
        ], [], [
            'trainerName' => 'nama',
            'trainerType' => 'tipe',
            'trainerUserId' => 'karyawan',
            'trainerExpertise' => 'keahlian',
            'trainerEmail' => 'email',
            'trainerPhone' => 'telepon',
            'trainerCompany' => 'perusahaan asal',
            'trainerAddress' => 'alamat',
        ]);

        $trainer = Trainers::create([
            'name' => $validated['trainerName'],
            'type' => $validated['trainerType'],
            'is_active' => true,
            'user_id' => $validated['trainerUserId'] ?: null,
            'expertise' => $validated['trainerExpertise'] ?: null,
            'email' => $validated['trainerEmail'] ?: null,
            'phone' => $validated['trainerPhone'] ?: null,
            'company' => $validated['trainerCompany'] ?: null,
            'address' => $validated['trainerAddress'] ?: null,
        ]);

        $this->meetingTrainerId = $trainer->id;
        $this->showCreateTrainerModal = false;

        Notification::make()->title('Pemateri baru berhasil ditambahkan')->success()->send();
    }

    private function resetTrainerForm(): void
    {
        $this->trainerName = '';
        $this->trainerType = 'external';
        $this->trainerUserId = null;
        $this->trainerExpertise = '';
        $this->trainerEmail = '';
        $this->trainerPhone = '';
        $this->trainerCompany = '';
        $this->trainerAddress = '';
        $this->resetErrorBag();
    }

    private function resetMeetingForm(): void
    {
        $this->meetingTitle = '';
        $this->meetingDescription = '';
        $this->meetingTrainerId = null;
        $this->meetingStartDate = null;
        $this->meetingEndDate = null;
        $this->meetingStartTime = null;
        $this->meetingEndTime = null;
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
        $this->meetingStartTime = $meeting->start_time ? substr($meeting->start_time, 0, 5) : null;
        $this->meetingEndTime = $meeting->end_time ? substr($meeting->end_time, 0, 5) : null;
        $this->resetErrorBag();
    }

    protected function meetingRules(): array
    {
        $rules = [
            'meetingDescription' => ['nullable', 'string'],
            'meetingTrainerId' => ['required', 'exists:trainers,id'],
            'meetingStartTime' => ['required', 'date_format:H:i'],
            'meetingEndTime' => ['required', 'date_format:H:i', 'after:meetingStartTime'],
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
            'start_time' => $validated['meetingStartTime'],
            'end_time' => $validated['meetingEndTime'],
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
        $this->quizIsGraduationRequirement = false;
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
            'is_graduation_requirement' => $this->quizIsGraduationRequirement,
        ]);

        $this->addingQuizForMeetingId = null;

        Notification::make()->title('Quiz berhasil ditambahkan')->success()->send();
    }

    public function removeQuiz(string $id): void
    {
        $this->assertCanEdit();

        $meetingQuiz = LmsMeetingQuizzes::whereHas('meeting', fn ($q) => $q->where('course_id', $this->course->id))->findOrFail($id);

        abort_if($meetingQuiz->results()->exists(), 403);

        $meetingQuiz->delete();

        Notification::make()->title('Quiz dihapus')->success()->send();
    }

    /** "Isi Absensi" cuma boleh dipakai kalau pelatihan sudah dipublish — sebelum publish,
     * peserta/pertemuan bisa masih berubah-ubah, absensi belum relevan untuk diisi. */
    public function getCanFillAttendanceProperty(): bool
    {
        return $this->canEditDetail && $this->course->status === LsmCourseStatus::Published->value;
    }

    public function openAttendanceView(string $meetingId): void
    {
        $this->attendanceMeetingId = null;
        $this->attendanceViewMeetingId = $meetingId;
    }

    public function closeAttendanceView(): void
    {
        $this->attendanceViewMeetingId = null;
    }

    public function getAttendanceViewRowsProperty()
    {
        if (! $this->attendanceViewMeetingId) {
            return collect();
        }

        return $this->buildAttendanceRows($this->attendanceViewMeetingId);
    }

    private function buildAttendanceRows(string $meetingId)
    {
        $existing = LmsCourseMeetingUsers::where('meeting_id', $meetingId)
            ->whereIn('user_id', $this->allParticipants->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        return $this->allParticipants->map(function ($participant) use ($existing) {
            $row = $existing->get($participant->user_id);

            return (object) [
                'name' => $participant->user?->name ?? '-',
                'masuk' => (bool) $row?->absen_masuk,
                'pulang' => (bool) $row?->absen_pulang,
                'keterangan' => $row?->keterangan_kehadiran,
            ];
        });
    }

    public function exportAttendance(string $meetingId)
    {
        $meeting = $this->course->meetings()->findOrFail($meetingId);

        $rows = $this->buildAttendanceRows($meetingId)->map(fn ($row) => (array) $row)->all();

        $fileName = 'Absensi - ' . $this->course->title . ' - ' . $meeting->title . '.xlsx';

        return Excel::download(
            new MeetingAttendanceExport(
                rows: $rows,
                courseTitle: $this->course->title,
                meetingTitle: $meeting->title,
                meetingDate: $meeting->start_date ? \Carbon\Carbon::parse($meeting->start_date)->translatedFormat('d M Y') : null,
            ),
            $fileName
        );
    }

    public function openAttendanceMode(string $meetingId): void
    {
        abort_unless($this->canFillAttendance, 403);

        $this->attendanceViewMeetingId = null;

        $existing = LmsCourseMeetingUsers::where('meeting_id', $meetingId)
            ->whereIn('user_id', $this->allParticipants->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        $this->attendanceForm = $this->allParticipants->mapWithKeys(function ($participant) use ($existing) {
            $row = $existing->get($participant->user_id);

            return [$participant->user_id => [
                'masuk' => (bool) $row?->absen_masuk,
                'pulang' => (bool) $row?->absen_pulang,
                'keterangan' => (string) ($row?->keterangan_kehadiran ?? ''),
            ]];
        })->all();

        $this->attendanceMeetingId = $meetingId;
        $this->attendanceSearch = '';
    }

    public function closeAttendanceMode(): void
    {
        $this->attendanceMeetingId = null;
        $this->attendanceForm = [];
        $this->attendanceSearch = '';
    }

    public function saveAttendance(): void
    {
        abort_unless($this->canFillAttendance, 403);

        $meeting = $this->course->meetings()->findOrFail($this->attendanceMeetingId);

        foreach ($this->attendanceForm as $userId => $entry) {
            LmsCourseMeetingUsers::updateOrCreate(
                ['course_id' => $this->course->id, 'meeting_id' => $meeting->id, 'user_id' => $userId],
                [
                    'absen_masuk' => (bool) ($entry['masuk'] ?? false),
                    'absen_pulang' => (bool) ($entry['pulang'] ?? false),
                    'keterangan_kehadiran' => $entry['keterangan'] !== '' ? $entry['keterangan'] : null,
                ]
            );
        }

        $this->closeAttendanceMode();

        Notification::make()->title('Absensi berhasil disimpan')->success()->send();
    }

    public function getFeedbacksProperty()
    {
        return $this->course->feedbacks()->with('respondent')->latest()->get();
    }

    public function getAverageRatingProperty(): float
    {
        return round($this->feedbacks->avg('rating') ?? 0, 2);
    }

    public function getHonorContextProperty(): ?TrainingCategoryEnum
    {
        return $this->course->category?->training_category;
    }

    public function getUsesScoredHonorProperty(): bool
    {
        return $this->honorContext?->usesScoredHonor() ?? false;
    }

    public function getSelectedHonorMeetingProperty()
    {
        if (! $this->selectedHonorMeetingId) {
            return null;
        }

        return $this->course->meetings()->with('trainer')->find($this->selectedHonorMeetingId);
    }

    public function getHonorVariablesProperty()
    {
        if (! $this->usesScoredHonor || ! $this->honorContext) {
            return collect();
        }

        return app(MeetingHonorariumService::class)->variablesForContext($this->honorContext->value);
    }

    /** Hasil final yang sudah tersimpan (kalau ada) untuk trainer pertemuan yang lagi dipilih. */
    public function getExistingHonorResultProperty(): ?TrainerAssessmentResults
    {
        $meeting = $this->selectedHonorMeeting;

        if (! $meeting || ! $meeting->trainer_id) {
            return null;
        }

        return TrainerAssessmentResults::where('course_id', $this->course->id)
            ->where('trainer_id', $meeting->trainer_id)
            ->first();
    }

    public function selectHonorMeeting(string $meetingId): void
    {
        $this->selectedHonorMeetingId = $meetingId;
        $this->honorCategorySelections = [];
        $this->honorFixedAmount = '';
        $this->resetErrorBag();

        $meeting = $this->selectedHonorMeeting;
        if (! $meeting) {
            return;
        }

        if ($this->usesScoredHonor) {
            $assessment = $meeting->honorariumAssessment()->with('details')->first();
            if ($assessment) {
                foreach ($assessment->details as $detail) {
                    $this->honorCategorySelections[$detail->variable_id] = $detail->category_id;
                }
            }
        } else {
            $result = $this->existingHonorResult;
            if ($result) {
                $this->honorFixedAmount = (string) $result->honor_amount;
            }
        }
    }

    public function saveHonorScored(): void
    {
        $this->assertCanEdit();

        $meeting = $this->selectedHonorMeeting;
        abort_unless($meeting, 404);

        $variableIds = $this->honorVariables->pluck('id');

        foreach ($variableIds as $variableId) {
            if (empty($this->honorCategorySelections[$variableId])) {
                $this->addError('honorCategorySelections', 'Semua variabel wajib dipilih salah satu kategorinya.');

                return;
            }
        }

        app(MeetingHonorariumService::class)->saveScoredAssessment(
            $meeting,
            array_intersect_key($this->honorCategorySelections, array_flip($variableIds->all()))
        );

        Notification::make()->title('Penilaian honorarium berhasil disimpan')->success()->send();
    }

    public function saveHonorFixed(): void
    {
        $this->assertCanEdit();

        $meeting = $this->selectedHonorMeeting;
        abort_unless($meeting, 404);

        $validated = $this->validate([
            'honorFixedAmount' => ['required', 'numeric', 'min:0'],
        ]);

        app(MeetingHonorariumService::class)->saveFixedAmount($meeting, (float) $validated['honorFixedAmount']);

        Notification::make()->title('Gaji pemateri berhasil disimpan')->success()->send();
    }

    public function render()
    {
        return view('livewire.lms.lms-course-detail-page');
    }
}
