<?php

namespace App\Http\Resources\Task;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskReportListResource extends JsonResource
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
        ];
    }
}
