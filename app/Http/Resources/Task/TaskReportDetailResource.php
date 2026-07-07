<?php

namespace App\Http\Resources\Task;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskReportDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'week_number' => $this->week_number,
            'week_start' => $this->week_start,
            'week_end' => $this->week_end,
            'quote' => $this->quote ?? null,
            'process_target_chart' => $this->chart_data,
            'completed_tasks' => TaskListResource::collection($this->completedTasks),
            'high_risk_tasks' => TaskListResource::collection($this->highRiskTasks),
            'next_week_tasks' => TaskListResource::collection($this->nextWeekTasks),
        ];
    }
}
