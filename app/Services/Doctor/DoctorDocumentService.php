<?php

namespace App\Services\Doctor;

use App\Models\AuditLog;
use App\Models\Doctor\Doctor;
use App\Models\Doctor\DoctorDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Simpan dokumen dokter: file dienkripsi (Crypt) di disk privat 'local' dengan nama acak, dibuka lewat
 * route filament.dokter.dokumen.show. Dipakai tab Dokumen (halaman edit) dan form Tambah Dokter.
 */
class DoctorDocumentService
{
    /** @param array{document_type: string, document_number?: ?string, issued_at?: ?string, expired_at?: ?string, status?: string, notes?: ?string} $meta */
    public function store(Doctor $doctor, array $meta, TemporaryUploadedFile|UploadedFile $file): DoctorDocument
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = 'doctor-documents/'.$doctor->id.'/'.Str::ulid().'.'.$ext;

        Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($file->getRealPath())));

        $document = $doctor->documents()->create([
            ...$meta,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $this->audit($document, 'unggah');

        return $document;
    }

    public function audit(DoctorDocument $document, string $aksi): void
    {
        AuditLog::record('doctor_document', Auth::user()?->company_id, 'doctor_document:'.$document->id, ucfirst($aksi)." dokumen dokter #{$document->doctor_id}: {$document->file_name}", null, null);
    }
}
