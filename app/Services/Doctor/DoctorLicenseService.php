<?php

namespace App\Services\Doctor;

use App\Models\Doctor\Doctor;
use App\Models\Doctor\DoctorLicense;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Simpan lisensi dokter; kalau ada file scan, disimpan sebagai dokumen (terenkripsi) dan ditautkan. Dipakai tab Lisensi & form Tambah Dokter. */
class DoctorLicenseService
{
    public function __construct(private DoctorDocumentService $documents) {}

    /** @param array{jenis: string, nomor?: ?string, tanggal_terbit?: ?string, tanggal_berlaku?: ?string, tanggal_berakhir?: ?string, status?: string, notes?: ?string} $data */
    public function store(Doctor $doctor, array $data, TemporaryUploadedFile|UploadedFile|null $file = null): DoctorLicense
    {
        $documentId = $file
            ? $this->documents->store($doctor, [
                'document_type' => $data['jenis'],
                'document_number' => $data['nomor'] ?? null,
                'issued_at' => $data['tanggal_terbit'] ?? null,
                'expired_at' => $data['tanggal_berakhir'] ?? null,
            ], $file)->id
            : null;

        return $doctor->licenses()->create([...$data, 'doctor_document_id' => $documentId]);
    }
}
