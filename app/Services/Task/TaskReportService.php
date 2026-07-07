<?php

namespace App\Services\Task;

use App\Models\Task\TaskReport;
use App\Repositories\Task\TaskReportRepository;
use App\Repositories\Task\TaskRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class TaskReportService
{
    public function __construct(
        protected TaskRepository $taskRepository,
        protected TaskReportRepository $taskReportRepository,
    ) {}

    public function generateMissingReports(): void
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $completedTasks = $this->taskRepository->getCompletedTasksByUser($user->id);
        if ($completedTasks->isEmpty()) {
            return;
        }

        $existingReports = $this->taskReportRepository->getExistingReportsByUser($user->id);

        $completedTasks
            ->groupBy(fn($task) => Carbon::parse($task->completed_date)->startOfWeek()->format('Y-m-d'))
            ->each(function ($tasks, $weekStart) use ($user, $companyId, $existingReports) {
                $start = Carbon::parse($weekStart);
                $end = $start->copy()->endOfWeek();

                if ($end->gte(Carbon::now()->startOfWeek())) {
                    return;
                }

                $weekKey = $start->year . '-' . $start->isoWeek();
                if (in_array($weekKey, $existingReports, true)) {
                    return;
                }

                $this->taskReportRepository->create([
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'week_start' => $start,
                    'week_end' => $end,
                    'year' => $start->year,
                    'week_number' => $start->isoWeek(),
                ]);
            });
    }

    public function getReportsForCurrentUser()
    {
        $user = Auth::user();
        return $this->taskReportRepository->getReportsForUser($user->id);
    }

    public function getReportDetail(string $id)
    {
        $user = Auth::user();

        $report = $this->taskReportRepository->findByIdAndUser($id, $user->id);

        if (! $report) {
            throw new ModelNotFoundException("Weekly report not found.");
        }

        return $report;
    }

    public function updateQuote(string $id, string $quote): TaskReport
    {
        $user = Auth::user();

        $report = $this->taskReportRepository->findByIdAndUser($id, $user->id);

        if (! $report) {
            throw new ModelNotFoundException("Weekly report not found.");
        }

        return $this->taskReportRepository->update($report, ['quote' => $quote]);
    }
}
