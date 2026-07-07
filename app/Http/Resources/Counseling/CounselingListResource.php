<?php

namespace App\Http\Resources\Counseling;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CounselingListResource extends JsonResource
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
            'status' => $this->status,
            'responded_at' => $this->responded_at,
            'created_at' => $this->created_at,
        ];
    }
}
