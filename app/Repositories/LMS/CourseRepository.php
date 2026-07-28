<?php

namespace App\Repositories\LMS;

use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourseFeedbackAnswer;
use App\Models\LMS\LmsCourseFeedbacks;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsFeedbackQuestion;
use App\Models\LMS\LmsMeetings;

class CourseRepository
{
    public function getAllCategories()
    {
        return LmsCategories::orderBy('name')->get();
    }

    public function getAllCourse(?string $keyword = null, ?string $category = null, int $limit = 10)
    {
        $query = LmsCourses::query()->with('category')->where('status', 'published');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                  ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        if ($category) {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', 'like', '%' . $category . '%');
            });
        }

        return $query->orderBy('title')->paginate($limit);
    }

    public function getCourseById(string $id)
    {
        return LmsCourses::with('category', 'meetings', 'meetings.materials', 'meetings.quizzes.quiz', 'meetings.attendance', 'courseUsers.meetingUsers')->find($id)->fresh();
    }

    public function getMeetingByCourseId(string $courseId)
    {
        return LmsMeetings::where('course_id', $courseId)->get();
    }

    public function getMeetingById(string $meetingId)
    {
        return LmsMeetings::find($meetingId);
    }

    public function getActiveFeedbackQuestions()
    {
        return LmsFeedbackQuestion::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function storeFeedback(array $data)
    {
        $courseFeedback = LmsCourseFeedbacks::create([
            'course_id' => $data['course_id'],
            'user_id' => $data['user_id'],
            'rating' => round(collect($data['answers'])->avg('score')),
            'feedback' => $data['feedback'] ?? null,
        ]);

        foreach ($data['answers'] as $answer) {
            LmsCourseFeedbackAnswer::create([
                'course_feedback_id' => $courseFeedback->id,
                'question_id' => $answer['question_id'],
                'score' => $answer['score'],
            ]);
        }

        return $courseFeedback;
    }

    public function getSuggestionsKeywords(string $partialKeyword, int $limit = 5)
    {
        return LmsCourses::where('title', 'like', '%' . $partialKeyword . '%')
            ->orWhere('code', 'like', '%' . $partialKeyword . '%')
            ->limit($limit)
            ->pluck('title')
            ->unique()
            ->values();
    }

}
