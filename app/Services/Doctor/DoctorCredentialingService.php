<?php

namespace App\Services\Doctor;

use App\Enum\Doctor\CredentialingStatus;
use App\Models\AuditLog;
use App\Models\Doctor\CredentialingCase;
use App\Models\Doctor\Doctor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Simpan case credentialing: PDF final (kalau diunggah) disimpan sebagai dokumen terenkripsi lalu ditautkan;
 * PDF lama TIDAK dihapus (tetap di tab Dokumen) supaya riwayat tidak tertimpa. Tiap perubahan status
 * dicatat di audit log (status bebas dipilih admin, tanpa urutan wajib).
 */
class DoctorCredentialingService
{
    public function __construct(private DoctorDocumentService $documents) {}

    public function save(Doctor $doctor, array $data, TemporaryUploadedFile|UploadedFile|null $pdf = null, ?CredentialingCase $case = null): CredentialingCase
    {
        $data = collect($data)->except('file')->all();
        // Select berisi enum mengirim objek enum, bukan string.
        if (($data['status'] ?? null) instanceof CredentialingStatus) {
            $data['status'] = $data['status']->value;
        }
        $oldStatus = $case?->status;

        if ($pdf) {
            $data['doctor_document_id'] = $this->documents->store($doctor, [
                'document_type' => 'CREDENTIALING',
                'document_number' => $data['nomor'] ?? null,
                'issued_at' => $data['tanggal_pengajuan'] ?? null,
            ], $pdf)->id;
        }

        if ($case) {
            $case->update($data);
        } else {
            $case = $doctor->credentialingCases()->create($data + ['created_by' => Auth::id()]);
        }

        if ($oldStatus !== $case->status) {
            $label = fn (?string $s) => $s ? (CredentialingStatus::tryFrom($s)?->getLabel() ?? $s) : '-';
            AuditLog::record('doctor_credentialing', Auth::user()?->company_id, 'doctor_credentialing:'.$case->id,
                "Credentialing dokter #{$doctor->id}: status {$label($oldStatus)} -> {$label($case->status)}", $oldStatus, $case->status);
        }

        return $case;
    }
}
