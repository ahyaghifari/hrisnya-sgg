<?php

use App\Http\Controllers\Api\Attendance\AttendanceRequestController;
use App\Http\Controllers\Api\Coaching\CoachingController;
use App\Http\Controllers\Api\Counseling\CounselingController;
use App\Http\Controllers\Api\JobOpening\JobOpeningController;
use App\Http\Controllers\Api\Task\TaskController;
use App\Http\Controllers\Api\Task\TaskReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {

    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/request-reset-password', [\App\Http\Controllers\Api\AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [\App\Http\Controllers\Api\AuthController::class, 'resetPassword']);

    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/change-password', [\App\Http\Controllers\Api\AuthController::class, 'changePassword'])->middleware('auth:sanctum');

});

Route::prefix('master')->group(function () {
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('attendance/request-types', [AttendanceRequestController::class, 'getMasterAttendanceReqTypes']);
        Route::get('departments/other', [TaskController::class, 'getOtherDepartments']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [\App\Http\Controllers\Api\UserController::class, 'show']);

    Route::prefix('lms')->group(function () {
        Route::get('/categories', [\App\Http\Controllers\Api\LMS\CourseController::class, 'getListCategories']);
        Route::get('/suggestions-keywords', [\App\Http\Controllers\Api\LMS\CourseController::class, 'suggetstionsKeywords']);
        Route::get('/courses', [\App\Http\Controllers\Api\LMS\CourseController::class, 'getAllCourses']);
        Route::get('/courses/{id}', [\App\Http\Controllers\Api\LMS\CourseController::class, 'getCourseById']);

        Route::post('/courses/{id}/follow', [\App\Http\Controllers\Api\LMS\CourseUserController::class, 'assignUser']);

        Route::get('/quiz/{meetingQuizId}', [\App\Http\Controllers\Api\LMS\QuizController::class, 'show']);
        Route::post('/quiz/{meetingQuizId}/submit', [\App\Http\Controllers\Api\LMS\QuizController::class, 'submit']);

        Route::post('/meeting/{meetingId}/clock-in', [\App\Http\Controllers\Api\LMS\AttendanceController::class, 'clockIn'])->name('lms.attendance.clock-in');

        Route::post('/courses/feedback', [\App\Http\Controllers\Api\LMS\CourseController::class, 'storeFeedback']);
        Route::get('/followed-courses', [\App\Http\Controllers\Api\LMS\CourseUserController::class, 'getFollowedCourses']);

        Route::get('/ranks', [\App\Http\Controllers\Api\LMS\CourseController::class, 'getRanksByUserId']);

        Route::get('/courses/{courseUserId}/certificate', [\App\Http\Controllers\Api\LMS\CourseUserController::class, 'downloadCertificate']);
    });

    Route::prefix('attendance')->group(function () {
        Route::get('/requests', [AttendanceRequestController::class, 'getListAttendanceRequestsByUserId']);
        Route::get('/requests/{id}', [AttendanceRequestController::class, 'findAttendanceRequestById']);

        Route::post('/overtime', [AttendanceRequestController::class, 'storeOvertimeRequest']);
        Route::post('/leave', [AttendanceRequestController::class, 'storeLeaveRequest']);
        Route::post('/permission', [AttendanceRequestController::class, 'storePermissionRequest']);

        Route::get('/leave/balance/{typeId}', [AttendanceRequestController::class, 'getLeaveUserBalance']);
    });

    Route::prefix('tasks')->group(function () {
        Route::prefix('reports')->group(function () {
            Route::get('/', [TaskReportController::class, 'getReports']);
            Route::get('/{id}', [TaskReportController::class, 'getDetailReport']);
            Route::put('/{id}/quote', [TaskReportController::class, 'updateQuote']);
        });

        Route::get('/services', [TaskController::class, 'getMasterTaskService']);
        Route::get('/reviewers', [TaskController::class, 'getReviewers']);

        Route::post('/self', [TaskController::class, 'storeSelfTask']);
        Route::get('/{id}', [TaskController::class, 'getTaskDetail']);
        Route::put('/{id}', [TaskController::class, 'updateTask']);
        Route::put('/{id}/review', [TaskController::class, 'reviewTask']);
        Route::post('/', [TaskController::class, 'storeTask']);

        Route::get('/', [TaskController::class, 'getTasks']);
    });

    Route::prefix('counselings')->group(function () {
        Route::post('/', [CounselingController::class, 'store']);
        Route::get('/', [CounselingController::class, 'index']);
        Route::get('/{id}', [CounselingController::class, 'show']);
    });

    Route::prefix('coachings')->group(function () {
        Route::get('/', [CoachingController::class, 'index']);
        Route::get('/{id}', [CoachingController::class, 'show']);
        Route::put('/{id}', [CoachingController::class, 'update']);
    });
});

Route::get('/sop', [\App\Http\Controllers\Api\SopController::class, 'index']);
Route::get('/sop/{id}/download', [\App\Http\Controllers\Api\SopController::class, 'download']);

Route::prefix('job-openings')->group(function () {
    Route::get('/', [JobOpeningController::class, 'index']);
    Route::get('/{id}', [JobOpeningController::class, 'show']);
});
