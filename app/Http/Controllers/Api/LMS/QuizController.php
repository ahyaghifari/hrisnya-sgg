<?php

namespace App\Http\Controllers\Api\LMS;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LMS\LmsRespondent;
use App\Repositories\LMS\QuizRepository;
use App\Services\LMS\QuizService;
use App\Services\LMS\RespondentService;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    protected $quizService;
    protected $quizRepo;
    protected $respondentService;

    public function __construct(QuizService $quizService, QuizRepository $quizRepo, RespondentService $respondentService)
    {
        $this->quizService = $quizService;
        $this->quizRepo = $quizRepo;
        $this->respondentService = $respondentService;
    }

    public function show($meetingQuizId)
    {
        try {
            $quiz = $this->quizService->getQuizDetail($meetingQuizId);

            if (!$quiz) {
                return ApiResponseHelper::notFound('Quiz not found');
            }

            return ApiResponseHelper::success($quiz, 'Quiz retrieved successfully', 200);
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Internal Server Error', 500, $e->getMessage());
        }
    }

    public function submit(Request $request, $meetingQuizId)
    {
        try {
            $userId = auth('sanctum')->id();

            $meetingQuiz = $this->quizRepo->getMeetingQuizById($meetingQuizId);

            if (!$meetingQuiz) {
                return ApiResponseHelper::notFound('Quiz not found');
            }

            $respondent = $this->respondentService->resolveForEmployee($userId, $meetingQuiz->meeting->course_id, LmsRespondent::CATEGORY_KUIS);

            $request->validate([
                'answers' => 'required|array',
                'answers.*.question_id' => 'required|string',
                'answers.*.answer_id' => 'nullable|string',
                'answers.*.answer_text' => 'nullable|string'
            ]);

            $result = $this->quizService->submitQuiz($meetingQuizId, $request->answers, $respondent->id, $userId);

            $message = isset($result['is_submitted']) && $result['is_submitted']
                ? 'You have already submitted this quiz.'
                : 'Quiz submitted successfully.';

            return ApiResponseHelper::success($result, $message, 200);
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Internal Server Error', 500, $e->getMessage());
        }
    }
}
