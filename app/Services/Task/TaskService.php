<?php

namespace App\Services\Task;

use App\Enum\Task\TaskStatus;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Task\Task;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Master\DepartmentRepository;
use App\Repositories\Task\TaskRepository;
use App\Repositories\Task\TaskServiceRepository;
use App\Repositories\User\UserRepository;
use App\Services\Approval\ApprovalCreationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        protected TaskServiceRepository $taskServiceRepository,
        protected TaskRepository $taskRepository,
        protected DepartmentRepository $departmentRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected UserRepository $userRepository,
    ) {}

    public function getAllTaskServices(): Collection
    {
        return $this->taskServiceRepository->getAllTaskServices();
    }

    public function getOtherDepartments(): Collection
    {
        $user = Auth::user();
        return $this->departmentRepository->getOtherDepartmentsByUserId($user->id);
    }

    public function getReviewers(): Collection
    {
        $user = Auth::user();
        return $this->userRepository->getReviewers($user);
    }

    public function getTasks(?string $code = null, ?bool $isHighRisk = false): Collection
    {
        $user = Auth::user();
        return $this->taskRepository->getTasksByUser($user->id, $code, $isHighRisk);
    }

    public function getTaskDetail(string $taskId): Task
    {
        $user = Auth::user();
        return $this->taskRepository->getTaskDetailByUser($taskId, $user->id);
    }

    public function createTask(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = Auth::user();

            $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
                moduleType: 'task_request',
                companyId: auth('sanctum')->user()->company_id
            );

            if (!$masterFlow) {
                throw new \Exception('No active approval flow found for task requests for this company.');
            }

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($request->department_id),
                'service_id' => $request->service_id,
                'department_id' => $request->department_id,
                'company_id' => $user->company_id,
                'requirement' => $request->requirement,
                'created_by' => $user->id,
                'is_self_assigned' => false,
                'status' => TaskStatus::PENDING->value,
                'sla_reviewer_id' => $user->id
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('tasks/attachments', 'public');
                    $this->taskRepository->addAttachment($task->id, $path);
                }
            }

            // Create approval snapshot
            $this->approvalCreationService->createSnapshot(
                moduleType: 'task_request',
                moduleId: $task->id,
                flow: $masterFlow,
                createdBy: $user->id,
                companyId: auth('sanctum')->user()->company_id,
                userId: $user->id
            );

            return $task->load('attachments');
        });
    }

    public function createSelfTask(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = Auth::user();

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($user->profile->department->id),
                'service_id' => $request->service_id,
                'department_id' => $user->profile->department->id,
                'company_id' => $user->company_id,
                'requirement' => $request->requirement,
                'due_date' => $request->due_date,
                'total_cost' => $request->total_cost,
                'unit_cost' => $request->unit_cost,
                'sla_reviewer_id' => $request->sla_reviewer_id,
                'created_by' => auth()->id(),
                'assigned_to' => auth()->id(),
                'is_self_assigned' => true,
                'responded_date' => Carbon::now(),
                'status' => TaskStatus::IN_PROGRESS->value,
            ]);

            return $task->load('attachments');
        });
    }

    private function generateCode(string $departmentId): string
    {
        $department = $this->departmentRepository->getDepartmentById($departmentId);
        $prefix = strtoupper(substr(preg_replace('/\s+/', '', $department->name), 0, 3));

        $lastTask = $this->taskRepository->getLastTaskByDepartment($department?->id);

        $lastNumber = 0;
        if ($lastTask && preg_match('/' . $prefix . '(\d{5})/', $lastTask->code, $matches)) {
            $lastNumber = (int) $matches[1];
        }
        $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        return $prefix . $nextNumber;
    }

    public function updateTask(string $taskId, $request)
    {
        return DB::transaction(function () use ($taskId, $request) {
            $user = Auth::user();
            $task = $this->taskRepository->getTaskDetailByUser($taskId, $user->id);

            $updateData = $request->only([
                'completed_date',
                'total_cost',
                'unit_cost',
                'risk_level',
                'collaboration_note',
                'root_cause',
                'solution',
            ]);

            $updateData['is_overdue'] = $task->due_date && $request->completed_at > $task->due_date;

            $this->taskRepository->update($taskId, $updateData);

            if ($request->filled('proofs_to_delete')) {
                $this->taskRepository->deleteProofs($request->proofs_to_delete);
            }

            if ($request->hasFile('proofs')) {
                foreach ($request->file('proofs') as $file) {
                    $path = $file->store('tasks/proofs', 'public');
                    $this->taskRepository->addProof($taskId, $path);
                }
            }

            return $this->taskRepository->getTaskDetailByUser($taskId, $user->id);
        });
    }

    public function reviewTask(string $taskId, $request)
    {
        return DB::transaction(function () use ($taskId, $request) {
            $user = Auth::user();

            $task = $this->taskRepository->getTaskDetailByUser($taskId, $user->id);

            if ($task->sla_reviewer_id !== $user->id) {
                throw new \Exception('You are not authorized to review this task.');
            }

            if ($task->review) {
                throw new \Exception('This task has already been reviewed.');
            }

            $reviewData = [
                'task_id' => $taskId,
                'reviewer_id' => $user->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ];

            $this->taskRepository->addReview($taskId, $reviewData);

            $updateData = [
                'status' => TaskStatus::COMPLETE->value,
            ];
            $this->taskRepository->update($taskId, $updateData);

            return $this->taskRepository->getTaskDetailByUser($taskId, $user->id);
        });
    }
}
