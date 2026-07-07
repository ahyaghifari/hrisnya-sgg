<?php

namespace App\Http\Resources\Coaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachingListResource extends JsonResource
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
            'status' => $this->status,
            'employee_condition' => $this->employee_condition,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
