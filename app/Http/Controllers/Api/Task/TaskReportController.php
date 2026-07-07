<?php

namespace App\Http\Controllers\Api\Task;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ReviewTaskRequest;
use App\Http\Requests\Task\StoreSelfTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\Master\DepartmentListResource;
use App\Http\Resources\Task\TaskDetailResource;
use App\Http\Resources\Task\TaskListResource;
use App\Http\Resources\Task\TaskReportDetailResource;
use App\Http\Resources\Task\TaskReportListResource;
use App\Http\Resources\Task\TaskServiceResource;
use App\Services\Task\TaskReportService;
use App\Services\Task\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskReportController extends Controller
{
    public function __construct(
        protected TaskReportService $taskReportService
    ) {}

    public function getReports(): JsonResponse
    {
        try {
            $this->taskReportService->generateMissingReports();
            $reports = $this->taskReportService->getReportsForCurrentUser();

            return ApiResponseHelper::success(TaskReportListResource::collection($reports), 'Successfully retrieved Weekly reports.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve Weekly reports.',
                500,
                $e->getMessage()
            );
        }
    }

    public function getDetailReport(string $id): JsonResponse
    {
        try {
            $report = $this->taskReportService->getReportDetail($id);

            return ApiResponseHelper::success(
                new TaskReportDetailResource($report),
                'Successfully retrieved weekly report detail.'
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseHelper::error('Weekly report not found.', 404);
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve weekly report detail.',
                500,
                $e->getMessage()
            );
        }
    }

    public function updateQuote(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'quote' => 'required|string|max:1000',
            ]);

            $report = $this->taskReportService->updateQuote($id, $request->quote);

            return ApiResponseHelper::success(
                new TaskReportListResource($report),
                'Weekly report quote updated successfully.'
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseHelper::error('Report not found.', 404);
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to update weekly report quote.',
                500,
                $e->getMessage()
            );
        }
    }
}
