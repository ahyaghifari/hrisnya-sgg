<?php

namespace App\Models\Sp;

use App\Contracts\Signable;
use App\Enum\DocumentType;
use App\Models\Concerns\HasDocumentSignatures;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berita Acara - 1:1 dengan employee_sps (instruksi user 2026-09-25). Catatan investigasi HC-GA
 * sebelum SP terbit, HR-internal (TIDAK ditampilkan ke karyawan lewat portal self-service - lihat
 * SuratPeringatanDetailPage).
 *
 * implements Signable TERPISAH dari EmployeeSp (instruksi user 2026-09-28: dokumen beda, TTD
 * dibedakan) - registry/hash/kode verifikasi sendiri, BUKAN ikut paket SP induknya. `sp_reports`
 * tidak punya kolom penanda tangan sendiri (tidak ada alur approval terpisah untuk Berita Acara),
 * jadi penanda tangannya dipinjam dari SP induk (created_by/knows_by) - lihat SpService::afterIssued().
 */
class SpReport extends Model implements Signable
{
    use HasUlids;
    use HasDocumentSignatures;

    protected $fillable = [
        'sp_id',
        'nomor',
        'kejadian',
        'dasar_klarifikasi',
        'subject_clarification',
        'analysis',
        'kesimpulan',
        'followup_plan',
    ];

    public function sp(): BelongsTo
    {
        return $this->belongsTo(EmployeeSp::class, 'sp_id');
    }

    /** @return array<string, mixed> */
    public function signablePayload(): array
    {
        return [
            'nomor' => $this->nomor,
            'sp_id' => $this->sp_id,
            'kejadian' => $this->kejadian,
            'dasar_klarifikasi' => $this->dasar_klarifikasi,
            'subject_clarification' => $this->subject_clarification,
            'analysis' => $this->analysis,
            'kesimpulan' => $this->kesimpulan,
            'followup_plan' => $this->followup_plan,
        ];
    }

    public function documentNumber(): string
    {
        return $this->nomor ?? "BA-{$this->sp_id}";
    }

    public function documentTypeLabel(): string
    {
        return 'Berita Acara';
    }

    public function documentType(): DocumentType
    {
        return DocumentType::BERITA_ACARA;
    }

    /** Ikut status batal SP induknya - Berita Acara tidak punya jalur cancel sendiri. */
    public function isRevoked(): bool
    {
        return $this->sp?->isCancelled() ?? false;
    }

    public function revokedReason(): ?string
    {
        return $this->sp?->cancelled_reason;
    }

    public function expectedSignerRoles(): array
    {
        return [
            'created_by' => 'Dibuat oleh',
            'knows_by' => 'Mengetahui',
        ];
    }

    /** Dipinjam dari SP induk - sp_reports tidak punya kolom penanda tangan sendiri (§4 docs
     *  TTE Fase 1). */
    public function assignedSigners(): array
    {
        return [
            'created_by' => $this->sp?->createdBy,
            'knows_by' => $this->sp?->knowsBy,
        ];
    }
}
