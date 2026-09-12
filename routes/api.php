<?php

use App\Http\Controllers\Api\Attendance\AttendanceRequestController;
use App\Http\Controllers\Api\Attendance\HolidayController;
use App\Http\Controllers\Api\Coaching\CoachingController;
use App\Http\Controllers\Api\Counseling\CounselingController;
use App\Http\Controllers\Api\JobOpening\JobOpeningController;
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
    });
});

Route::prefix('attendance')->group(function () {
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::get('/holidays/kemerdekaan', [HolidayController::class, 'showIndependenceDay']);
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

        Route::get('/courses/feedback-questions', [\App\Http\Controllers\Api\LMS\CourseController::class, 'getFeedbackQuestions']);
        Route::post('/courses/feedback', [\App\Http\Controllers\Api\LMS\CourseController::class, 'storeFeedback']);
        Route::get('/followed-courses', [\App\Http\Controllers\Api\LMS\CourseUserController::class, 'getFollowedCourses']);

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

// Rekap presensi divisi IT (holding) untuk otomasi n8n — HTTP Basic auth, 50 req/menit.
Route::prefix('n8n')->middleware('throttle:50,1')->group(function () {
    Route::get('/offduty', [\App\Http\Controllers\Api\N8n\DivisionAttendanceController::class, 'offduty']);
    Route::get('/absen', [\App\Http\Controllers\Api\N8n\DivisionAttendanceController::class, 'absen']);
});

// Direktori karyawan by NIP (nama/email/unit) — dipakai sistem lain isi data akun auto-
// provision dari SSO. HTTP Basic auth (services.integrasi), 50 req/menit, PII (beda dari
// /publik/unit-organisasi yang tanpa auth).
Route::prefix('integrasi')->middleware('throttle:50,1')->group(function () {
    Route::get('/karyawan/{nip}', [\App\Http\Controllers\Api\Integrasi\EmployeeDirectoryController::class, 'show']);
});

Route::get('/sop', [\App\Http\Controllers\Api\SopController::class, 'index']);
Route::get('/sop/{id}/download', [\App\Http\Controllers\Api\SopController::class, 'download']);

Route::prefix('job-openings')->group(function () {
    Route::get('/', [JobOpeningController::class, 'index']);
    Route::get('/{id}', [JobOpeningController::class, 'show']);
});
