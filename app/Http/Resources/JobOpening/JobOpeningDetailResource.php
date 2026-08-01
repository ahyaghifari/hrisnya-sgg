<?php

namespace App\Http\Resources\JobOpening;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOpeningDetailResource extends JsonResource
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
            'company' => $this->company->name,
            'job_position' => $this->display_name,
            'description' => $this->description,
            'requirements' => $this->requirements
        ];
    }
}
