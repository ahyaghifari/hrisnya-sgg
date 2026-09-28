<?php

namespace App\Services\Document;

use App\Enum\DocumentType;
use App\Models\Document\Document;
use App\Models\Sp\EmployeeSp;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Lapisan GENERIK untuk "dokumen apa saja yang menunggu tanda tangan saya" + aksi menandatangani
 * (TTE Fase 1, instruksi user 2026-09-28: bukan cuma buat SP - Surat Tugas dan jenis dokumen lain
 * nanti lewat jalur yang sama, tanpa halaman/modal terpisah per jenis). Modal & halaman tanda
 * tangan (SignDocumentsPage) HANYA bicara ke service ini, tidak pernah import model SP/dst
 * langsung - supaya menambah jenis dokumen baru cukup: (1) model itu implements Signable, (2)
 * tambah 1 baris di TYPES di bawah.
 */
class DocumentSigningService
{
    /**
     * Jenis dokumen yang ikut di-scan untuk "menunggu tanda tangan saya" (§ di atas - satu-satunya
     * tempat yang perlu disentuh saat jenis dokumen baru siap implement Signable, mis. Surat
     * Tugas). Query pencarian TETAP per-jenis (bukan 1 query generik lintas tabel) karena field
     * yang dipakai assignedSigners() beda-beda struktur tiap model sumber - lihat pendingIdsFor().
     */
    private const TYPES = [
        DocumentType::SP,
        DocumentType::BERITA_ACARA,
    ];

    /** @return Collection<int, Document> */
    public function pendingFor(User $user): Collection
    {
        return Document::query()
            ->whereIn('document_type', array_map(fn (DocumentType $t) => $t->value, self::TYPES))
            ->with(['documentable', 'signatures'])
            ->get()
            ->filter(fn (Document $document) => $document->pendingRoleFor($user) !== null)
            ->values();
    }

    /**
     * Menandatangani 1 peran pada 1 Document - otorisasi (apakah $actor memang ditugaskan peran
     * itu) dicek generik lewat Signable::assignedSigners(), berlaku untuk jenis dokumen apa pun.
     *
     * Berita Acara SP SENGAJA ikut ditandatangani dalam aksi yang sama kalau $document ternyata
     * EmployeeSp yang punya report - ini satu-satunya bagian yang SPESIFIK-SP (bundling 2
     * dokumen jadi 1 konfirmasi karena keduanya tindakan approval yang sama secara nyata),
     * ditulis eksplisit di sini, bukan disembunyikan sebagai "magic" di Signable/trait generik.
     */
    public function sign(Document $document, User $actor): void
    {
        $document->loadMissing(['documentable', 'signatures']);

        $role = $document->pendingRoleFor($actor);

        abort_unless($role !== null, 403, 'Anda tidak ditugaskan menandatangani dokumen ini, atau sudah menandatangani sebelumnya.');

        $document->documentable->sign($actor, $role);

        if ($document->documentable instanceof EmployeeSp && $document->documentable->report) {
            $report = $document->documentable->report;
            $report->loadMissing('document.signatures');

            if (! $report->document?->signatures->contains('signer_role', $role)) {
                $report->sign($actor, $role);
            }
        }
    }
}
