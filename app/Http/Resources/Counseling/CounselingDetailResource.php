<?php

namespace App\Http\Resources\Counseling;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CounselingDetailResource extends JsonResource
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
            'issue' => $this->issue,
            'response' => $this->response,
            'solution' => $this->solution,
            'status' => $this->status,
            'responded_at' => $this->responded_at,
            'user' => [
                'name' => $this->user?->name,
                'department' => $this->user?->profile?->department?->name,
                'division' => $this->user?->profile?->division?->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
