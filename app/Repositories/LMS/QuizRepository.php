<?php

namespace App\Repositories\LMS;

use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizQuestions;
use App\Models\LMS\LmsQuizUserAnswers;
use App\Models\LMS\LmsQuizUserResults;
use App\Models\LMS\QuestionOptions;

class QuizRepository
{
    public function getMeetingQuizById(string $meetingQuizId)
    {
        return LmsMeetingQuizzes::with('quiz.questions.options')->find($meetingQuizId);
    }

    public function getMeetingQuizByMeetingId(string $meetingId)
    {
        return LmsMeetingQuizzes::with('quiz')->where('meeting_id', $meetingId)->get();
    }

    public function getQuizQuestions(string $quizId)
    {
        return LmsQuizQuestions::with('questionBank.options')
            ->where('quiz_id', $quizId)
            ->get();
    }

    public function isOptionCorrectById(string $optionId): bool
    {
        return QuestionOptions::where('id', $optionId)->where('is_correct', true)->exists();
    }

    public function saveUserAnswer(array $data)
    {
        return LmsQuizUserAnswers::create($data);
    }

    public function saveUserResult(array $data)
    {
        return LmsQuizUserResults::updateOrCreate(
            [
                'meeting_quiz_id' => $data['meeting_quiz_id'],
                'user_id' => $data['user_id'],
            ],
            $data
        );
    }

    public function findUserResultByMeetingQuizId(string $meetingQuizId, string $userId)
    {
        return LmsQuizUserResults::where('meeting_quiz_id', $meetingQuizId)
            ->where('user_id', $userId)
            ->first();
    }

    public function getUserResultsByMeetingId(string $meetingId)
    {
        return LmsQuizUserResults::whereHas('meetingQuiz', function ($query) use ($meetingId) {
            $query->where('meeting_id', $meetingId);
        })->get();
    }

    public function getPendingReviewResults()
    {
        return LmsQuizUserResults::with(['user', 'quiz', 'meetingQuiz.meeting.course'])
            ->where('status', 'pending_review')
            ->latest('completed_at')
            ->get();
    }

    public function findResultById(string $resultId)
    {
        return LmsQuizUserResults::with(['user', 'quiz', 'meetingQuiz.meeting.course'])->find($resultId);
    }

    public function getEssayAnswers(string $meetingQuizId, string $userId)
    {
        return LmsQuizUserAnswers::with('question')
            ->where('meeting_quiz_id', $meetingQuizId)
            ->where('user_id', $userId)
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->get();
    }

    public function getAllAnswers(string $meetingQuizId, string $userId)
    {
        return LmsQuizUserAnswers::where('meeting_quiz_id', $meetingQuizId)
            ->where('user_id', $userId)
            ->get();
    }

    public function updateAnswerScore(string $answerId, int $earnedPoints)
    {
        return LmsQuizUserAnswers::where('id', $answerId)->update(['earned_points' => $earnedPoints]);
    }
}