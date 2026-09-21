<?php

namespace App\Http\Resources\Coaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachingDetailResource extends JsonResource
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
            'title' => $this->title,
            'goals' => $this->goals,
            'employee_condition' => $this->employee_condition,
            'solution' => $this->solution,
            'improvement_action' => $this->improvement_action,
            'improvement_due_date' => $this->improvement_due_date,
        ];
    }
}
