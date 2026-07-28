<?php

namespace App\Repositories\LMS;

use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizQuestions;
use App\Models\LMS\LmsQuizAnswers;
use App\Models\LMS\LmsQuizResults;
use App\Models\LMS\LmsQuestionOptions;

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
        return LmsQuestionOptions::where('id', $optionId)->where('is_correct', true)->exists();
    }

    public function saveUserAnswer(array $data)
    {
        return LmsQuizAnswers::create($data);
    }

    public function saveUserResult(array $data)
    {
        return LmsQuizResults::updateOrCreate(
            [
                'meeting_quiz_id' => $data['meeting_quiz_id'],
                'respondent_id' => $data['respondent_id'],
            ],
            $data
        );
    }

    public function findUserResultByMeetingQuizId(string $meetingQuizId, string $respondentId)
    {
        return LmsQuizResults::where('meeting_quiz_id', $meetingQuizId)
            ->where('respondent_id', $respondentId)
            ->first();
    }

    public function getUserResultsByMeetingId(string $meetingId)
    {
        return LmsQuizResults::whereHas('meetingQuiz', function ($query) use ($meetingId) {
            $query->where('meeting_id', $meetingId);
        })->get();
    }

    public function getPendingReviewResults()
    {
        return LmsQuizResults::with(['respondent', 'quiz', 'meetingQuiz.meeting.course'])
            ->where('status', 'pending_review')
            ->latest('completed_at')
            ->get();
    }

    public function findResultById(string $resultId)
    {
        return LmsQuizResults::with(['respondent', 'quiz', 'meetingQuiz.meeting.course'])->find($resultId);
    }

    public function getEssayAnswers(string $meetingQuizId, string $respondentId)
    {
        return LmsQuizAnswers::with('question')
            ->where('meeting_quiz_id', $meetingQuizId)
            ->where('respondent_id', $respondentId)
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->get();
    }

    public function getAllAnswers(string $meetingQuizId, string $respondentId)
    {
        return LmsQuizAnswers::where('meeting_quiz_id', $meetingQuizId)
            ->where('respondent_id', $respondentId)
            ->get();
    }

    public function updateAnswerScore(string $answerId, int $earnedPoints)
    {
        return LmsQuizAnswers::where('id', $answerId)->update(['earned_points' => $earnedPoints]);
    }
}
