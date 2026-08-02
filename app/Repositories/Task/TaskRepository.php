<?php

namespace App\Repositories\Task;

use App\Models\Task\Task;
use App\Models\Task\TaskAttachment;
use App\Models\Task\TaskProof;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class TaskRepository
{
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

    public function addAttachment(string $taskId, array $data): TaskAttachment
    {
        return TaskAttachment::create([
            'task_id' => $taskId,
            'type' => $data['type'] ?? TaskAttachment::TYPE_FILE,
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'url' => $data['url'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? null,
        ]);
    }

    public function deleteAttachment(string $attachmentId): void
    {
        $attachment = TaskAttachment::find($attachmentId);

        if (! $attachment) {
            return;
        }

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();
    }
}
