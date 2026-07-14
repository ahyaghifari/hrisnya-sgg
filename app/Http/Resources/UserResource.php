<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // profile_picture di-cast array, tapi sebagian baris lama tersimpan sebagai JSON string
        // biasa (bukan array) — akses [0] pada string malah ambil 1 karakter pertama, bukan
        // error. Cek tipenya dulu biar aman buat kedua bentuk data.
        $rawPicture = $this->profile?->profile_picture;
        $photoPath = is_array($rawPicture) ? ($rawPicture[0] ?? null) : $rawPicture;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'nip' => $this->nip,
            'company_name' => $this->company?->name,
            'profile_picture' => $photoPath && is_string($photoPath) ? url(Storage::url($photoPath)) : null,
            'department' => $this->profile->department->name ?? null,
            'division' => $this->profile->division->name ?? null,
            'sub_division' => $this->profile->sub_division->name ?? null,
            'position' => $this->profile->position->name ?? null,
            'pangkat' => $this->profile->pangkat->name ?? null,
            'golongan' => $this->profile->golongan->name ?? null,
            'employee_type' => $this->profile->employeeType->name ?? null,
        ];
    }
}
