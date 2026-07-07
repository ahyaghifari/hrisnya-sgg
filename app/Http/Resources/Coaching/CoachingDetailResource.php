<?php

namespace App\Http\Resources\Coaching;

use App\Enum\CoachingStatus;
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

            'proofs' => $this->groupedProofs(),
        ];
    }

    protected function groupedProofs()
    {
        return $this->proofs
            ->groupBy(function ($proof) {
                return $proof->created_at->format('Y-m-d');
            })
            ->map(function ($items, $date) {
                return $items->groupBy('status')->map(function ($statusItems, $status) use ($date) {
                    return [
                        'date' => $date,
                        'status' => $status,
                        'proofs' => CoachingProofResource::collection($statusItems),
                    ];
                })->values();
            })
            ->flatten(1)
            ->values();
    }
}
