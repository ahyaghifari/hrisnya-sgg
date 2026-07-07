<?php

namespace App\Repositories\Task;

use App\Enum\Task\TaskRiskLevel;
use App\Models\Task\Task;
use App\Models\Task\TaskAttachment;
use App\Models\Task\TaskProof;
use App\Models\Task\TaskReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class TaskRepository
{
    public function getTasksByUser(string $userId, ?string $code = null, ?bool $isHighRisk = false): Collection
    {
        return Task::query()
            ->where(function ($query) use ($userId) {
                $query->where('assigned_to', $userId)
                    ->orWhere('sla_reviewer_id', $userId);
            })
            ->when($code, function ($query, $code) {
                $query->where('code', 'like', '%' . $code . '%');
            })
            ->when($isHighRisk, function ($query) {
                $query->where('risk_level', TaskRiskLevel::HIGH->value)
                    ->orWhere('is_overdue', true);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function getTaskDetailByUser(string $taskId, string $userId): Task
    {
        $task = Task::query()
            ->where('id', $taskId)
            ->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)
                    ->orWhere('sla_reviewer_id', $userId);
            })
            ->first();

        if (! $task) {
            throw new ModelNotFoundException();
        }

        return $task;
    }

    public function getCompletedTasksByUser(string $userId): Collection
    {
        return Task::where('assigned_to', $userId)
            ->whereNotNull('completed_date')
            ->orderBy('completed_date')
            ->get();
    }

    public function findTaskById(string $taskId): Task
    {
        return Task::find($taskId);
    }

    public function getLastTaskByDepartment(?string $departmentId)
    {
        return Task::query()
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            ->orderByDesc('created_at')
            ->first();
    }

    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function update(string $taskId, array $data): bool
    {
        return Task::where('id', $taskId)->update($data);
    }

    public function addProof(string $taskId, string $path)
    {
        return TaskProof::create([
            'task_id' => $taskId,
            'file_path' => $path,
        ]);
    }

    public function deleteProofs(array $proofIds): void
    {
        $proofs = TaskProof::whereIn('id', $proofIds)->get();

        foreach ($proofs as $proof) {
            if ($proof->file_path && Storage::disk('public')->exists($proof->file_path)) {
                Storage::disk('public')->delete($proof->file_path);
            }

            $proof->delete();
        }
    }

    public function addAttachment(string $taskId, string $path): TaskAttachment
    {
        return TaskAttachment::create([
            'task_id' => $taskId,
            'file_path' => $path,
        ]);
    }

    public function addReview(string $taskId, array $reviewData): TaskReview
    {
        return TaskReview::create([
            'task_id' => $taskId,
            'reviewer_id' => $reviewData['reviewer_id'],
            'rating' => $reviewData['rating'],
            'comment' => $reviewData['comment'] ?? null,
        ]);
    }
}
