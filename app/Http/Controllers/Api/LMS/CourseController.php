<?php

namespace App\Http\Controllers\Api\LMS;

use App\Enum\CourseUserStatus;
use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\LMS\CategoriesResource;
use App\Http\Resources\LMS\CourseDetailsResource;
use App\Http\Resources\LMS\CoursesResource;
use App\Models\LMS\LmsRespondent;
use App\Services\LMS\CourseService;
use App\Services\LMS\RespondentService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(
        protected CourseService $courseService,
        protected RespondentService $respondentService
    ) {}

    public function getListCategories()
    {
        try {
            $categories = $this->courseService->getAllCategories();
    
            if ($categories->isEmpty()) {
                return ApiResponseHelper::notFound('No categories found.');
            }
    
            return ApiResponseHelper::success(CategoriesResource::collection($categories), 'Categories retrieved successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while retrieving categories.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function suggetstionsKeywords(Request $request)
    {
        try {
            $keyword = $request->query('keyword', '');
            $limit = $request->query('limit', 5);
            $suggestions = $this->courseService->getSuggestionsKeywords($keyword, $limit);
    
            if ($suggestions->isEmpty()) {
                return ApiResponseHelper::notFound('No keyword suggestions found.');
            }
    
            return ApiResponseHelper::success($suggestions, 'Keyword suggestions retrieved successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while retrieving keyword suggestions.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function getAllCourses(Request $request)
    {
        try {
            $keyword = $request->query('keyword');
            $category = $request->query('category');
            $limit = $request->query('limit', 10);
    
            $courses = $this->courseService->getAllCourse($keyword, $category, $limit);
    
            if ($courses->isEmpty()) {
                return ApiResponseHelper::notFound('No courses found.');
            }
    
            return ApiResponseHelper::successPaginated(CoursesResource::collection($courses), 'Courses retrieved successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while retrieving courses.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function getCourseById(string $id)
    {
        try {
            $course = $this->courseService->getCourseById($id);
    
            if (!$course) {
                return ApiResponseHelper::notFound('Course not found.');
            }

            return ApiResponseHelper::success(new CourseDetailsResource($course), 'Course retrieved successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while retrieving the course.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function getFeedbackQuestions()
    {
        try {
            $questions = $this->courseService->getFeedbackQuestions();

            return ApiResponseHelper::success($questions, 'Feedback questions retrieved successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while retrieving feedback questions.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function storeFeedback(Request $request)
    {
        $request->validate([
            'course_id' => 'required|string|exists:lms_courses,id',
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|string|exists:lms_feedback_questions,id',
            'answers.*.score' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string',
        ]);

        try {
            $userId = auth('sanctum')->user()->id;
            $courseId = $request->input('course_id');

            $respondent = $this->respondentService->resolveForEmployee($userId, $courseId, LmsRespondent::CATEGORY_UMPAN_BALIK);

            $this->courseService->storeFeedback([
                'respondent_id' => $respondent->id,
                'course_id' => $courseId,
                'answers' => $request->input('answers'),
                'feedback' => $request->input('feedback'),
            ]);

            return ApiResponseHelper::success(null, 'Feedback submitted successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while submitting feedback.', 500, ['error' => $e->getMessage()]);
        }
    }

}
