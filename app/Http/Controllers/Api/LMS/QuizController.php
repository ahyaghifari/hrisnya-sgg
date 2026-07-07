<?php

namespace App\Http\Controllers\Api\LMS;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\QuizService;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    protected $quizService;

    public function __construct(QuizService $quizService)
    {
        $this->quizService = $quizService;
    }

    public function show($meetingQuizId)
    {
        try {
            $userId = auth('sanctum')->id();
            $quiz = $this->quizService->getQuizDetail($meetingQuizId, $userId);
    
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
    
            $request->validate([
                'answers' => 'required|array',
                'answers.*.question_id' => 'required|string',
                'answers.*.answer_id' => 'nullable|string',
                'answers.*.answer_text' => 'nullable|string'
            ]);
    
            $result = $this->quizService->submitQuiz($meetingQuizId, $request->answers, $userId);
    
            $message = isset($result['is_submitted']) && $result['is_submitted'] 
                ? 'You have already submitted this quiz.' 
                : 'Quiz submitted successfully.';

            return ApiResponseHelper::success($result, $message, 200);
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Internal Server Error', 500, $e->getMessage());
        }
    }
}