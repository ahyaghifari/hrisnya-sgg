<?php

namespace App\Http\Resources\LMS;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RankResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user_name' => $this->user ? $this->user->name : null,
            'profile_picture' => $this->user && $this->user->profile_picture ? asset(\Illuminate\Support\Facades\Storage::url($this->user->profile_picture)) : null,
            'rank' => $this->recalculated_rank ?? $this->rank,
        ];
    }
}
