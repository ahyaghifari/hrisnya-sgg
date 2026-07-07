<?php

namespace App\Http\Controllers\Api\Task;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ReviewTaskRequest;
use App\Http\Requests\Task\StoreSelfTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\Master\DepartmentListResource;
use App\Http\Resources\Task\ReviewerListResource;
use App\Http\Resources\Task\TaskDetailResource;
use App\Http\Resources\Task\TaskListResource;
use App\Http\Resources\Task\TaskServiceResource;
use App\Services\Task\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    public function getMasterTaskService(): JsonResponse
    {
        try {
            $data = $this->taskService->getAllTaskServices();

            return ApiResponseHelper::success(TaskServiceResource::collection($data), 'Successfully retrieved task services.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve task services.',
                500,
                $e->getMessage()
            );
        }
    }

    public function getOtherDepartments(): JsonResponse
    {
        try {
            $data = $this->taskService->getOtherDepartments();

            return ApiResponseHelper::success(DepartmentListResource::collection($data), 'Successfully retrieved departments.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve departments.',
                500,
                $e->getMessage()
            );
        }
    }

    public function getReviewers(): JsonResponse
    {
        try {
            $data = $this->taskService->getReviewers();

            return ApiResponseHelper::success(
                ReviewerListResource::collection($data),
                'Successfully retrieved reviewers.'
            );
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve reviewers.',
                500,
                $e->getMessage()
            );
        }
    }

    public function getTasks(Request $request): JsonResponse
    {
        try {
            $code = $request->get('code');
            $isHighRisk = $request->query('is_high_risk', false);
            $isHighRiskBool = filter_var($isHighRisk, FILTER_VALIDATE_BOOLEAN);

            $tasks = $this->taskService->getTasks($code, $isHighRiskBool);

            return ApiResponseHelper::success(
                TaskListResource::collection($tasks),
                'Successfully retrieved tasks.'
            );
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve tasks.',
                500,
                $e->getMessage()
            );
        }
    }

    public function getTaskDetail(string $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTaskDetail($id);

            return ApiResponseHelper::success(
                new TaskDetailResource($task),
                'Successfully retrieved task detail.'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseHelper::notFound('Task not found.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve task detail.',
                500,
                $e->getMessage()
            );
        }
    }

    public function storeTask(StoreTaskRequest $request): JsonResponse
    {
        try {
            $task = $this->taskService->createTask($request);

            return ApiResponseHelper::success(TaskListResource::make($task), 'Successfully store task.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to store task.',
                500,
                $e->getMessage()
            );
        }
    }

    public function storeSelfTask(StoreSelfTaskRequest $request): JsonResponse
    {
        try {
            $task = $this->taskService->createSelfTask($request);

            return ApiResponseHelper::success(TaskListResource::make($task), 'Successfully store task.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to store task.',
                500,
                $e->getMessage()
            );
        }
    }

    public function updateTask(UpdateTaskRequest $request, string $id): JsonResponse
    {
        try {
            $task = $this->taskService->updateTask($id, $request);

            return ApiResponseHelper::success(
                new TaskDetailResource($task),
                'Task successfully updated.'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseHelper::notFound('Task not found.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to update task.',
                500,
                $e->getMessage()
            );
        }
    }

    public function reviewTask(ReviewTaskRequest $request, string $id): JsonResponse
    {
        try {
            $task = $this->taskService->reviewTask($id, $request);

            return ApiResponseHelper::success(
                new TaskDetailResource($task),
                'Task successfully reviewed.'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseHelper::notFound('Task not found.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to submit review.',
                500,
                $e->getMessage()
            );
        }
    }
}
