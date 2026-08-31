<?php

namespace App\Services\LMS;

use App\Enum\CourseUserStatus;
use App\Events\LmsProgresUpdated;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizQuestions;
use App\Models\LMS\LmsQuizzes;
use App\Repositories\LMS\CourseRepository;
use App\Repositories\LMS\CourseUserRepository;
use App\Repositories\LMS\MeetingUserRepository;
use App\Repositories\LMS\QuizRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizService
{
    protected $quizRepo;
    protected $meetingUserRepository;
    protected $courseUserRepository;
    protected $courseRepositories;

    public function __construct(
        QuizRepository $quizRepo,
        MeetingUserRepository $meetingUserRepository,
        CourseUserRepository $courseUserRepository,
        CourseRepository $courseRepositories
    )
    {
        $this->quizRepo = $quizRepo;
        $this->meetingUserRepository = $meetingUserRepository;
        $this->courseUserRepository = $courseUserRepository;
        $this->courseRepositories = $courseRepositories;
    }

    /**
     * $excludeEssay = true untuk kuis publik (§7.4 planning) — soal esai tidak pernah
     * dikirim/diterima di jalur publik, cuma pilihan ganda.
     */
    public function getQuizDetail(string $meetingQuizId, bool $excludeEssay = false)
    {
        $meetingQuiz = $this->quizRepo->getMeetingQuizById($meetingQuizId);
        if (!$meetingQuiz) return null;

        $quiz = $meetingQuiz->quiz;
        $questions = $quiz->questions->sortBy('sort_order');

        if ($excludeEssay) {
            $questions = $questions->reject(fn ($q) => $q->type === 'essay');
        }

        $formattedQuestions = $questions->values()->map(function ($q, $index) {
            $options = $q->options->shuffle()->map(function ($o, $optionIndex) {
                return [
                    'id' => $o->id,
                    'text' => chr(65 + $optionIndex) . '. ' . $this->cleanOptionText($o->option_text)
                ];
            });

            return [
                'id' => $q->id,
                'no' => $index + 1,
                'type' => $q->type,
                'question_text' => trim(strip_tags($q->question)),
                'options' => $q->type !== 'essay' ? $options : []
            ];
        });

        return [
            'id' => $meetingQuiz->id,
            'title' => $quiz->title,
            'duration' => $meetingQuiz->duration,
            'questions' => $formattedQuestions
        ];
    }

    /**
     * $employeeUserId cuma diisi untuk peserta terdaftar (portal/mobile) — dipakai untuk
     * validasi enrollment (CourseUserStatus::APPROVED) dan update progress pertemuan.
     * Jalur publik memanggil dengan $employeeUserId = null: tanpa validasi enrollment,
     * tanpa update progress (lms_course_meeting_users tidak pernah menyentuh respondent publik).
     */
    public function submitQuiz(string $meetingQuizId, array $answers, string $respondentId, ?string $employeeUserId = null)
    {
        $existingResult = $this->quizRepo->findUserResultByMeetingQuizId($meetingQuizId, $respondentId);
        if ($existingResult) {
            return [
                'is_submitted' => true,
                'user_score' => $existingResult->total_score,
                'max_score' => $existingResult->max_score,
                'has_essay' => $this->quizRepo->getQuizQuestions($existingResult->quiz_id)
                    ->pluck('questionBank.type')->contains('essay'),
            ];
        }

        $meetingQuiz = $this->quizRepo->getMeetingQuizById($meetingQuizId);
        $meeting = $this->courseRepositories->getMeetingById($meetingQuiz->meeting_id);

        if ($employeeUserId) {
            $courseUser = $this->courseUserRepository->getByUserIdAndCourseId($employeeUserId, $meeting->course_id);

            if (!$courseUser || $courseUser->status !== CourseUserStatus::APPROVED->value) {
                return [
                    'success' => false,
                    'message' => 'Your course enrollment has not been approved yet.',
                ];
            }
        }

        $quiz = $meetingQuiz->quiz;
        $questionsPivot = $quiz->pivotTable;

        $totalMultipleChoiceQuestions = $questionsPivot->filter(function ($pivot) {
            return $pivot->questionBank->type !== 'essay';
        })->count();

        $pointsPerMultipleChoice = $totalMultipleChoiceQuestions > 0 ? (100 / $totalMultipleChoiceQuestions) : 0;

        $totalAutoScore = 0;
        $maxAutoScore = 100;
        $hasEssay = false;
        $correct_count = 0;
        $wrong_count = 0;

        foreach ($answers as $ans) {
            $questionPivot = $questionsPivot->firstWhere('question_bank_id', $ans['question_id']);
            if (!$questionPivot) continue;

            $question = $questionPivot->questionBank;

            $isCorrect = null;
            $earnedPoints = 0;

            if ($question->type === 'essay') {
                $hasEssay = true;
                $userAnswer = $ans['answer_text'] ?? '';
                $isCorrect = false;
                $maxAutoScore += $question->max_score;
            } else {
                $userAnswer = $ans['answer_id'] ?? '';
                $isCorrect = $this->quizRepo->isOptionCorrectById($userAnswer);
                $earnedPoints = $isCorrect ? $pointsPerMultipleChoice : 0;
                if ($isCorrect) {
                    $correct_count++;
                } else {
                    $wrong_count++;
                }
                $totalAutoScore += $earnedPoints;
            }

            $this->quizRepo->saveUserAnswer([
                'meeting_quiz_id' => $meetingQuizId,
                'quiz_id' => $quiz->id,
                'question_id' => $question->id,
                'respondent_id' => $respondentId,
                'user_answer' => $userAnswer,
                'is_correct' => $isCorrect,
                'earned_points' => $earnedPoints
            ]);
        }

        $status = $hasEssay ? 'pending_review' : 'completed';

        $this->quizRepo->saveUserResult([
            'meeting_quiz_id' => $meetingQuizId,
            'quiz_id' => $quiz->id,
            'respondent_id' => $respondentId,
            'total_score' => $totalAutoScore,
            'max_score' => $maxAutoScore,
            'correct_count' => $correct_count,
            'wrong_count' => $wrong_count,
            'is_completed' => true,
            'completed_at' => now(),
            'status' => $status,
        ]);

        if ($employeeUserId) {
            $this->updateMeetingUserData($employeeUserId, $meetingQuiz->meeting_id);
        }

        return [
            'user_score' => $totalAutoScore,
            'max_score' => $maxAutoScore,
            'has_essay' => $hasEssay,
            'status' => $status
        ];
    }

    /**
     * Admin menilai jawaban esai satu attempt quiz (1 meeting_quiz + 1 respondent). $scores: [answer_id => skor].
     * Skor MC yang sudah tersimpan tidak disentuh — cuma earned_points esai yang diupdate, lalu
     * total_score di-summasi ulang dari SEMUA jawaban (MC + esai) supaya konsisten dengan submitQuiz().
     * Esai cuma pernah muncul di jalur peserta terdaftar (kuis publik tidak pernah kirim esai),
     * jadi $result->respondent->user_id selalu terisi di sini.
     */
    public function reviewEssayAnswers(string $meetingQuizId, string $respondentId, array $scores, string $reviewerId): void
    {
        $result = $this->quizRepo->findUserResultByMeetingQuizId($meetingQuizId, $respondentId);
        abort_unless($result && $result->status === 'pending_review', 404);

        $essayAnswers = $this->quizRepo->getEssayAnswers($meetingQuizId, $respondentId);

        foreach ($essayAnswers as $answer) {
            if (!array_key_exists($answer->id, $scores)) {
                continue;
            }

            $maxScore = (int) $answer->question->max_score;
            $earnedPoints = max(0, min($maxScore, (int) $scores[$answer->id]));

            $this->quizRepo->updateAnswerScore($answer->id, $earnedPoints);
        }

        $totalScore = $this->quizRepo->getAllAnswers($meetingQuizId, $respondentId)->sum('earned_points');

        $this->quizRepo->saveUserResult([
            'meeting_quiz_id' => $meetingQuizId,
            'quiz_id' => $result->quiz_id,
            'respondent_id' => $respondentId,
            'total_score' => $totalScore,
            'max_score' => $result->max_score,
            'correct_count' => $result->correct_count,
            'wrong_count' => $result->wrong_count,
            'is_completed' => true,
            'completed_at' => $result->completed_at,
            'status' => 'completed',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        $employeeUserId = $result->respondent->user_id ?? null;

        if ($employeeUserId) {
            $this->updateMeetingUserData($employeeUserId, $this->quizRepo->getMeetingQuizById($meetingQuizId)->meeting_id);
        }
    }

    /**
     * ---- Bagian di bawah ini untuk portal CreateQuiz/QuizzesPage (authoring kuis) ----
     * Method-method di atas (getQuizDetail/submitQuiz/dst) untuk peserta MENGERJAKAN kuis;
     * ini untuk admin/portal MEMBUAT/MENGUBAH kuisnya. Sengaja digabung di 1 class yang sama
     * (bukan service baru) supaya tidak ada 2 "QuizService" yang membingungkan.
     */

    /**
     * @param  string[]  $orderedQuestionIds  urutan tampil soal di kuis ini
     */
    public function create(array $data, array $orderedQuestionIds): LmsQuizzes
    {
        return DB::transaction(function () use ($data, $orderedQuestionIds) {
            $quiz = LmsQuizzes::create([
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'created_by' => $data['created_by'] ?? Auth::id(),
            ]);

            $this->syncQuestions($quiz, $orderedQuestionIds);

            return $quiz->fresh();
        });
    }

    /**
     * @param  string[]  $orderedQuestionIds
     */
    public function updateQuiz(string $id, array $data, array $orderedQuestionIds): LmsQuizzes
    {
        $quiz = LmsQuizzes::findOrFail($id);

        return DB::transaction(function () use ($quiz, $data, $orderedQuestionIds) {
            $quiz->update([
                'category_id' => $data['category_id'],
                'title' => $data['title'],
            ]);

            $this->syncQuestions($quiz, $orderedQuestionIds);

            return $quiz->fresh();
        });
    }

    /**
     * Hapus kuis yang tidak dipakai di pelatihan manapun. FK lms_meeting_quizzes->lms_quizzes
     * pakai cascadeOnDelete (bukan restrict), jadi kalau tidak dijaga di sini hapus kuis akan
     * diam-diam ikut menghapus riwayat hasil kuis peserta (lms_quiz_answers/results via
     * meeting_quiz_id). Admin EditQuiz.php belum punya guard ini — sengaja tidak diikuti.
     */
    public function deleteQuiz(string $id): void
    {
        $quiz = LmsQuizzes::findOrFail($id);

        if (LmsMeetingQuizzes::where('quiz_id', $id)->exists()) {
            throw new \RuntimeException('Kuis ini sudah digunakan di pelatihan dan tidak bisa dihapus.');
        }

        $quiz->delete();
    }

    /**
     * Sebagian soal lama diketik dengan huruf pilihan sudah menempel di teksnya sendiri
     * (mis. "A. Untuk memenuhi persyaratan akreditasi") — kalau dibiarkan, huruf itu ikut
     * ter-acak posisinya waktu shuffle() di atas, sementara huruf baru yang ditambahkan
     * (chr(65+$optionIndex)) mengikuti posisi SETELAH shuffle, jadi dua-duanya beda dan
     * salah satunya jadi tidak sinkron. Buang huruf lama sebelum huruf baru ditambahkan.
     */
    protected function cleanOptionText(?string $text): string
    {
        $text = trim(strip_tags($text ?? ''));

        return preg_replace('/^[A-Za-z][\.\)]\s*/', '', $text);
    }

    /**
     * @param  string[]  $orderedQuestionIds
     */
    protected function syncQuestions(LmsQuizzes $quiz, array $orderedQuestionIds): void
    {
        $quiz->pivotTable()->whereNotIn('question_bank_id', $orderedQuestionIds)->delete();

        foreach ($orderedQuestionIds as $index => $questionId) {
            LmsQuizQuestions::updateOrCreate(
                ['quiz_id' => $quiz->id, 'question_bank_id' => $questionId],
                ['sort_order' => $index]
            );
        }

        $quiz->updateTotalScore();
    }

    public function updateMeetingUserData($userId, $meetingId)
    {
        $quizResult = $this->quizRepo->getUserResultsByMeetingId($meetingId);
        if ($quizResult->isEmpty()) {
            return;
        }

        $totalQuizCount = $this->quizRepo->getMeetingQuizByMeetingId($meetingId)->count();
        $userQuizResultCount = $quizResult->count();

        $averageScore = $quizResult->avg('total_score');
        $isCompletedQuiz = $quizResult->every('is_completed', true);

        $meetingUser = $this->meetingUserRepository->findByUserIdAndMeetingId($userId, $meetingId);

        $dataMeetingUser = [
            'quiz_completed' => $isCompletedQuiz,
            'quiz_score' => $averageScore,
        ];

        if (
            $meetingUser &&
            ($meetingUser->is_attended) &&
            $isCompletedQuiz &&
            $userQuizResultCount === $totalQuizCount
        ) {
            $dataMeetingUser['is_completed'] = true;
            $dataMeetingUser['completed_at'] = now();
        }

        $this->meetingUserRepository->updateByUserIdAndMeetingId($userId, $meetingId, $dataMeetingUser);

        // course_id di-resolve dari pertemuan, BUKAN dari $meetingUser — peserta yang mengikuti
        // kursus sendiri (lewat "Ikuti Pelatihan") bisa mengerjakan kuis tanpa punya baris
        // lms_course_meeting_users, jadi $meetingUser bisa null. Event progress kursus tetap
        // harus jalan supaya status lms_course_users maju (LmsProgresListener).
        $meeting = $this->courseRepositories->getMeetingById($meetingId);

        if ($meeting) {
            event(new LmsProgresUpdated(
                $meetingUser->user ?? \App\Models\User::find($userId),
                $this->courseRepositories->getCourseById($meeting->course_id)
            ));
        }
    }
}
