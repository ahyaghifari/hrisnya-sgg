<?php

namespace App\Services\LMS;

use App\Events\LmsProgresUpdated;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Repositories\LMS\CourseRepositories;
use App\Repositories\LMS\CourseUserRepository;
use App\Repositories\LMS\MeetingUserRepository;
use App\Repositories\LMS\QuizRepository;

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
        CourseRepositories $courseRepositories
    )
    {
        $this->quizRepo = $quizRepo;
        $this->meetingUserRepository = $meetingUserRepository;
        $this->courseUserRepository = $courseUserRepository;
        $this->courseRepositories = $courseRepositories;
    }

    public function getQuizDetail(string $meetingQuizId, string $userId)
    {
        $meetingQuiz = $this->quizRepo->getMeetingQuizById($meetingQuizId);
        if (!$meetingQuiz) return null;

        $quiz = $meetingQuiz->quiz;
        $questions = $quiz->questions->sortBy('sort_order');

        $formattedQuestions = $questions->values()->map(function ($q, $index) {
            $options = $q->options->shuffle()->map(function ($o, $optionIndex) {
                return [
                    'id' => $o->id,
                    'text' => chr(65 + $optionIndex) . '. ' . $o->option_text
                ];
            });

            return [
                'id' => $q->id,
                'no' => $index + 1,
                'type' => $q->type,
                'question_text' => $q->question,
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

    public function submitQuiz(string $meetingQuizId, array $answers, string $userId)
    {
        $existingResult = $this->quizRepo->findUserResultByMeetingQuizId($meetingQuizId, $userId);
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
                'user_id' => $userId,
                'user_answer' => $userAnswer,
                'is_correct' => $isCorrect,
                'earned_points' => $earnedPoints
            ]);
        }

        $status = $hasEssay ? 'pending_review' : 'completed';

        $this->quizRepo->saveUserResult([
            'meeting_quiz_id' => $meetingQuizId,
            'quiz_id' => $quiz->id,
            'user_id' => $userId,
            'total_score' => $totalAutoScore,
            'max_score' => $maxAutoScore,
            'correct_count' => $correct_count,
            'wrong_count' => $wrong_count,
            'is_completed' => true,
            'completed_at' => now()
        ]);

        $this->updateMeetingUserData($userId, $meetingQuiz->meeting_id);

        return [
            'user_score' => $totalAutoScore,
            'max_score' => $maxAutoScore,
            'has_essay' => $hasEssay,
            'status' => $status
        ];
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
        event(new LmsProgresUpdated(auth('sanctum')->user(), $this->courseRepositories->getCourseById($meetingUser->course_id)));
    }
}