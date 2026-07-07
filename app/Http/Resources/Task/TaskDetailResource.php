<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDetailResource extends JsonResource
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
            'code' => $this->code,
            'requirement' => $this->requirement,
            'status' => $this->status,
            'is_self_assigned' => (bool) $this->is_self_assigned,
            'total_cost' => $this->total_cost,
            'unit_cost' => $this->unit_cost,

            'risk_level' => $this->risk_level,
            'due_date' => $this->due_date,
            'responded_date' => $this->responded_date,
            'started_date' => $this->started_date,
            'completed_date' => $this->completed_date,
            'duration' => $this->duration,
            'overdue_days' => $this->overdue_days,

            'collaboration_note' => $this->collaboration_note,
            'root_cause' => $this->root_cause,
            'solution' => $this->solution,

            'service' => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
            ],
            'assigned_to' => [
                'id' => $this->assignedTo?->id,
                'name' => $this->assignedTo?->name,
            ],
            'sla_reviewer' => [
                'id' => $this->slaReviewer?->id,
                'name' => $this->slaReviewer?->name,
                'email' => $this->slaReviewer?->email,
                'department' => $this->slaReviewer?->profile?->department?->name,
                'division' => $this->slaReviewer?->profile?->division?->name,
            ],

            'attachments' => $this->attachments->map(fn ($file) => [
                'id' => $file->id,
                'file_url' => $file->file_url,
            ]),
            'proofs' => $this->proofs->map(fn ($proof) => [
                'id' => $proof->id,
                'file_url' => $proof->file_url,
            ]),

            'review' => $this->review ? [
                'rating' => $this->review->rating,
                'comment' => $this->review->comment,
            ] : null
        ];
    }
}
