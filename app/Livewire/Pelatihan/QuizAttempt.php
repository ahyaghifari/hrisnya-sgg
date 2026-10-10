<?php

namespace App\Livewire\Pelatihan;

use App\Enum\CourseUserStatus;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizResults;
use App\Models\LMS\LmsRespondent;
use App\Repositories\LMS\CourseUserRepository;
use App\Repositories\LMS\QuizRepository;
use App\Services\LMS\QuizService;
use App\Services\LMS\RespondentService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class QuizAttempt extends Component
{
    public LmsMeetingQuizzes $meetingQuiz;

    public bool $blocked = false;

    public bool $locked = false;

    public ?LmsQuizResults $result = null;

    public ?string $quizTitle = null;
    public ?int $duration = null;
    public ?string $trainerName = null;

    public string $respondentId;

    /** @var array<int, array{id: string, no: int, question_text: string, options: array}> soal pilihan ganda saja */
    public array $questions = [];

    /** @var array<string, string> question_id => option_id */
    public array $answers = [];

    public function mount(LmsMeetingQuizzes $meetingQuiz): void
    {
        $meetingQuiz->load('meeting.course.category', 'meeting.trainer', 'quiz');

        $this->meetingQuiz = $meetingQuiz;

        $this->respondentId = app(RespondentService::class)
            ->resolveForEmployee(Auth::id(), $meetingQuiz->meeting->course_id, LmsRespondent::CATEGORY_KUIS)
            ->id;

        // Header (judul, trainer, waktu pelaksanaan) tetap tampil di ketiga state (blocked/
        // hasil/form) — makanya di-set duluan sebelum gate approval & cek "sudah submit".
        $this->quizTitle = $meetingQuiz->quiz?->title;
        $this->duration = $meetingQuiz->duration;
        $this->trainerName = $meetingQuiz->meeting->trainer?->name;

        // Cek "sudah pernah submit" DULUAN, sebelum gate approval — mirror urutan asli di
        // QuizService::submitQuiz() (baris 68-77 sebelum baris 83-88). Status LmsCourseUsers
        // berubah dari "approved" ke "in_progress"/"completed" begitu quiz/attendance
        // pertama diselesaikan (lewat LmsProgresListener) — kalau gate approval dicek duluan,
        // user yang baru saja submit malah keblokir lihat hasilnya sendiri di kunjungan berikutnya.
        $existingResult = app(QuizRepository::class)->findUserResultByMeetingQuizId($meetingQuiz->id, $this->respondentId);

        if ($existingResult) {
            $this->result = $existingResult;

            return;
        }

        // Gate kunci — kuis baru default terkunci, admin buka manual lewat tombol "Buka" di
        // halaman detail pelatihan (semua pengerjaan kuis via HRIS, tidak ada lagi akses publik).
        if (! $meetingQuiz->is_unlocked) {
            $this->locked = true;

            return;
        }

        // Gate approval — QuizService::getQuizDetail() sendiri tidak menegakkan ini (cuma
        // submitQuiz() yang gate), jadi ditambal manual di sini sebelum soal ditampilkan.
        // Terima APPROVED *dan* IN_PROGRESS: begitu peserta menyelesaikan kuis/absensi
        // pertama, LmsProgresListener menggeser status approved -> in_progress — tanpa
        // IN_PROGRESS di whitelist, kuis pertemuan berikutnya yang sudah dibuka trainer
        // malah keblokir.
        $courseUser = app(CourseUserRepository::class)->getByUserIdAndCourseId(Auth::id(), $meetingQuiz->meeting->course_id);

        $allowedStatuses = [CourseUserStatus::APPROVED->value, CourseUserStatus::IN_PROGRESS->value];

        if (! $courseUser || ! in_array($courseUser->status, $allowedStatuses, true)) {
            $this->blocked = true;

            return;
        }

        $quiz = app(QuizService::class)->getQuizDetail($meetingQuiz->id);

        // Soal essay tidak dirender di portal dulu — selalu 0 poin & tanpa jalur review yang
        // berfungsi di backend, jadi tidak ada gunanya ditampilkan sebagai form isian.
        $this->questions = collect($quiz['questions'] ?? [])
            ->where('type', 'multiple_choice')
            ->values()
            ->all();

        // Jawaban yang sudah dipilih disimpan di session (bukan cuma memori komponen) supaya
        // refresh/tutup-buka browser tidak menghilangkannya — tapi logout tetap menghapusnya
        // (LoginController::destroy() memanggil session()->invalidate()), sesuai yang diminta.
        $this->answers = Session::get($this->sessionKey(), []);
    }

    protected function sessionKey(): string
    {
        return 'pelatihan.quiz_answers.' . $this->meetingQuiz->id;
    }

    // ponytail: sama persis dengan TrainingList::categoryAccent() — hash 4 baris, duplikasi
    // lebih murah daripada trait/service baru untuk 3 pemakaian.
    public function getAccentProperty(): string
    {
        $palette = ['navy', 'green', 'rust', 'teal', 'gold'];
        $categoryName = $this->meetingQuiz->meeting->course->category?->name;

        if (! $categoryName) {
            return $palette[0];
        }

        return $palette[crc32($categoryName) % count($palette)];
    }

    public function selectAnswer(string $questionId, string $optionId): void
    {
        $this->answers[$questionId] = $optionId;

        Session::put($this->sessionKey(), $this->answers);
    }

    public function submit(): void
    {
        $missing = collect($this->questions)
            ->pluck('id')
            ->reject(fn ($id) => filled($this->answers[$id] ?? null));

        if ($missing->isNotEmpty()) {
            Notification::make()
                ->title('Masih ada soal yang belum dijawab')
                ->danger()
                ->send();

            return;
        }

        $payload = collect($this->answers)
            ->map(fn ($optionId, $questionId) => ['question_id' => $questionId, 'answer_id' => $optionId])
            ->values()
            ->all();

        app(QuizService::class)->submitQuiz($this->meetingQuiz->id, $payload, $this->respondentId, Auth::id());

        $this->result = app(QuizRepository::class)->findUserResultByMeetingQuizId($this->meetingQuiz->id, $this->respondentId);
        $this->questions = [];
        Session::forget($this->sessionKey());

        Notification::make()
            ->title('Kuis berhasil dikirim')
            ->success()
            ->send();

        // View: scroll ke hasil di atas, lalu 5 detik kemudian balik ke detail pelatihan.
        $this->dispatch('quiz-submitted', url: route('pelatihan.show', ['course' => $this->meetingQuiz->meeting->course_id]));
    }

    public function render()
    {
        return view('livewire.pelatihan.quiz-attempt')
            ->layout('components.layout', ['title' => 'Kuis']);
    }
}
