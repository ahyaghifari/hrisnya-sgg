<?php

namespace App\Repositories\LMS;

use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourseFeedbacks;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\LmsRankings;
use App\Models\LMS\LmsUserHistories;

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
        return LmsCourses::with('category', 'meetings', 'userHistories', 'meetings.materials', 'meetings.quizzes.quiz', 'meetings.attendance', 'courseUsers.meetingUsers')->find($id)->fresh();
    }

    public function getMeetingByCourseId(string $courseId)
    {
        return LmsMeetings::where('course_id', $courseId)->get();
    }

    public function getMeetingById(string $meetingId)
    {
        return LmsMeetings::find($meetingId);
    }

    public function storeFeedback(array $data)
    {
        LmsCourseFeedbacks::create($data);
    }

    public function storeUserHistory(array $data)
    {
        LmsUserHistories::create($data);
    }

    public function upsertCourseRanking(array $data)
    {
        LmsRankings::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'course_id' => $data['course_id'],
            ],
            [
                'total_score' => $data['total_score'],
                'calculated_at' => $data['calculated_at'],
            ]
        );
    }

    public function getUserHistoryByUserIdAndCourseId(string $userId, string $courseId)
    {
        return LmsUserHistories::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
    }

    public function getRanksByUserId(array $filters = [])
    {
        $query = LmsRankings::with('course', 'user.profile');

        if (isset($filters['type']) && $filters['type'] === 'internal' && isset($filters['company_id'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('company_id', $filters['company_id']);
            });
        }

        $rankings = $query->get();

        $groupedByUser = $rankings->groupBy('user_id')->map(function ($userRankings) {
            $firstRanking = $userRankings->first();
            $avgScore = $userRankings->avg('total_score');

            $ranking = clone $firstRanking;
            $ranking->average_total_score = round($avgScore, 2);
            $ranking->courses_count = $userRankings->count();

            return $ranking;
        });

        $sortedRankings = $groupedByUser->sortBy([
            ['average_total_score', 'desc'],
            ['user.name', 'asc']
        ])->values();

        $rank = 1;
        foreach ($sortedRankings as $ranking) {
            $ranking->recalculated_rank = $rank++;
        }

        return $sortedRankings;
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
