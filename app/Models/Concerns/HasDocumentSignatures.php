<?php

namespace App\Models\Concerns;

use App\Models\Document\Document;
use App\Models\Document\DocumentSignature;
use App\Models\User;
use App\Services\Document\VerificationCodeGenerator;

/**
 * Dipasangkan dengan App\Contracts\Signable - tiap model dokumen (EmployeeSp, SpReport, dst)
 * `use HasDocumentSignatures;` biar tidak menulis ulang query registry/tanda tangan/kode
 * verifikasi yang sama. Lihat docs/2026-09-28-tte-qr-internal-fase-1.md §3.
 */
trait HasDocumentSignatures
{
    /** FIX/LOCK: isi dokumen terkunci (Document::fixed_at) - dipakai guard booted() model sumber. */
    public function isFixed(): bool
    {
        return $this->exists && $this->document()->whereNotNull('fixed_at')->exists();
    }

    public function document(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    /** Bikin baris registry `documents` kalau belum ada - dipanggil sekali di titik pemicu
     * sebelum sign() pertama kali. Idempotent - dokumen yang sudah punya registry tidak dobel.
     * Sekaligus FIX/LOCK isi (Document::fix); dokumen yang di-unfix (klarifikasi ulang) dikunci lagi di sini. */
    public function issueDocument(string $title, ?User $issuer = null): Document
    {
        /** @var \App\Contracts\Signable $this */
        if ($this->document) {
            $this->document->fix($issuer);

            return $this->document;
        }

        $document = $this->document()->create([
            'document_type'   => $this->documentType()->value,
            'document_number' => $this->documentNumber(),
            'title'           => $title,
            'issued_at'       => now(),
            'created_by'      => $issuer?->id,
        ]);
        $document->logEvent('document_created', $issuer);
        $document->fix($issuer);

        // Accessing `$this->document` above (kalau belum pernah di-load) sudah men-cache hasil
        // query pertama (null, karena baris belum ada) ke relation cache Eloquent - tanpa
        // setRelation() di sini, akses `$this->document` BERIKUTNYA (di sign()/ensureVerificationCode())
        // tetap kepental ke null lama itu, bukan baris yang barusan dibuat.
        $this->setRelation('document', $document);

        return $document;
    }

    /** Tanda tangan 1 peran ($role bebas per jenis dokumen, mis. 'created_by'/'knows_by') -
     * hash dihitung dari signablePayload() SEKARANG (saat sign dipanggil), disimpan sebagai
     * potret data final. Panggil issueDocument() dulu sebelum ini.
     *
     * Kode verifikasi/QR dibangkitkan BARENGAN di sini (instruksi user 2026-09-29) - PERSONAL
     * per TTD, bukan 1 kode dibagi rata ke semua peran (keputusan awal §0/§100
     * docs/2026-09-28-tte-qr-internal-fase-1.md, direvisi). Retry kalau kode tabrakan (peluangnya
     * sangat kecil, lihat VerificationCodeGenerator) daripada percaya unique() constraint doang
     * tanpa jaring pengaman aplikasi.
     *
     * ip_address/user_agent DIREKAM (instruksi user 2026-09-28, menutup gap "Audit Log" §15/§24
     * docs/HRIS_TTE_NonTersertifikasi_QR_Verification.md) - internal-only, TIDAK ditampilkan di
     * halaman verifikasi publik. request() bisa null saat dipanggil dari CLI/queue (mis. command
     * backfill nanti) - null-safe, jangan sampai signing gagal cuma karena tidak ada HTTP request.
     */
    public function sign(User $signer, string $role): DocumentSignature
    {
        /** @var \App\Contracts\Signable $this */
        $signature = $this->document->signatures()->create([
            'signer_role'     => $role,
            'signer_id'       => $signer->id,
            'signer_name'     => $signer->name,
            'signer_position' => $signer->activeJobPosition?->jobPosition?->name,
            'document_hash'   => hash('sha256', json_encode($this->signablePayload())),
            'signed_at'       => now(),
            'ip_address'      => request()?->ip(),
            'user_agent'      => request()?->userAgent(),
        ]);
        $this->document->logEvent('document_signed', $signer, ['role' => $role]);

        $type = $this->documentType();

        retry(5, fn () => $signature->verification()->create([
            'verification_code' => app(VerificationCodeGenerator::class)->generate($type),
        ]), 0, fn ($e) => $e instanceof \Illuminate\Database\QueryException);

        // Semua peran wajib sudah TTD -> FINAL (isFullySigned baca relation, buang cache dulu).
        $this->document->unsetRelation('signatures');
        if ($this->document->isFullySigned()) {
            // PDF dirender di queue (UI: "sedang diproses"). Kalau job gagal, dokumen difinalkan
            // lazy saat PDF-nya dibuka (DocumentFinalizer::contents).
            \App\Jobs\FinalizeDocumentJob::dispatch($this->document->id);
        }

        return $signature;
    }
}
