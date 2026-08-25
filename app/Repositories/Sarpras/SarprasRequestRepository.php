<?php

namespace App\Repositories\Sarpras;

use App\Models\Sarpras\SarprasRequest;
use App\Models\Sarpras\SarprasRequestItem;
use App\Models\Sarpras\SarprasRequestSignature;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class SarprasRequestRepository
{
    public function store(array $data): SarprasRequest
    {
        return SarprasRequest::create($data);
    }

    /** @param array<int, array{nama: string, spesifikasi?: ?string, jumlah: int, satuan?: string, harga_satuan?: ?float}> $items */
    public function storeItems(SarprasRequest $request, array $items): void
    {
        foreach ($items as $item) {
            $request->items()->create($item);
        }
    }

    /** @param array<int, \Illuminate\Http\UploadedFile> $files */
    public function storeAttachments(SarprasRequest $request, array $files, ?string $userId): void
    {
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $path = Storage::disk('public')->putFile("sarpras/{$request->id}", $file);

            $request->attachments()->create([
                'user_id' => $userId,
                'nama_asli' => $file->getClientOriginalName(),
                'nama_simpan' => $path,
                'mime' => $file->getMimeType(),
                'ukuran' => $file->getSize(),
            ]);
        }
    }

    public function storeSignature(SarprasRequest $request, string $peran, User $user, string $kode): SarprasRequestSignature
    {
        return $request->signatures()->create([
            'user_id' => $user->id,
            'peran' => $peran,
            'nama' => $user->name,
            'jabatan' => $user->profile?->jobPosition?->name,
            'kode' => $kode,
        ]);
    }

    public function updateItem(SarprasRequestItem $item, array $data): void
    {
        $item->update($data);
    }

    public function updateRequest(SarprasRequest $request, array $data): void
    {
        $request->update($data);
    }
}
