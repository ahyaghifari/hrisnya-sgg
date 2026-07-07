<?php

namespace App\Repositories\Task;

use App\Enum\Task\TaskRiskLevel;
use App\Models\Task\Task;
use App\Models\Task\TaskAttachment;
use App\Models\Task\TaskProof;
use App\Models\Task\TaskReport;
use App\Models\Task\TaskReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class TaskReportRepository
{
    public function getExistingReportsByUser(string $userId): array
    {
        return TaskReport::where('user_id', $userId)
            ->get(['year', 'week_number'])
            ->map(fn($r) => $r->year . '-' . $r->week_number)
            ->toArray();
    }

    public function create(array $data): TaskReport
    {
        return TaskReport::create($data);
    }

    public function getReportsForUser(string $userId)
    {
        return TaskReport::where('user_id', $userId)
            ->orderBy('week_start')
            ->get();
    }

    public function findByIdAndUser(string $id, string $userId)
    {
        return TaskReport::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function update(TaskReport $report, array $data): TaskReport
    {
        $report->update($data);
        return $report->refresh();
    }
}
