<?php

namespace App\Services\Sp;

use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cetak SP jadi PDF (instruksi user 2026-09-25) - pola persis PayrollService::download() (kop
 * surat = gambar background privat per company, storage/app/private/sp-bg/{code}.jpg, fallback
 * default.jpg, di-embed base64 di resources/views/pdf/sp/sp.blade.php).
 *
 * QR verifikasi (TTE Fase 1, docs/2026-09-28-tte-qr-internal-fase-1.md §7) di-generate ULANG
 * tiap PDF dirender (bukan disimpan sebagai file), TAPI isinya (URL /verify/{code}) selalu SAMA
 * selama TTD-nya tidak dihapus - `verification_code` dibangkitkan sekali per TTD saat sign
 * (HasDocumentSignatures::sign(), instruksi user 2026-09-29: 1 kode PERSONAL per penanda tangan,
 * bukan 1 kode dibagi rata ke semua peran), bukan tiap render. Format SVG (default
 * simple-qrcode) - tidak butuh ext-imagick yang tidak dijamin terpasang, dompdf render inline SVG
 * data URI dengan baik untuk pola sesederhana QR.
 */
class SpPdfService
{
    public function download(EmployeeSp $sp): StreamedResponse
    {
        $sp = $this->loadForPdf($sp);
        $pdf = Pdf::loadView('pdf.sp.sp', ['sp' => $sp, 'verification' => $this->verificationBySignerRole($sp)]);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->stream();
            },
            $this->filename($sp),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Beda dari download() — dompdf stream() defaultnya Content-Disposition: inline (buka di
     * tab baru/PDF viewer browser), TIDAK dibungkus response()->streamDownload() yang selalu
     * memaksa attachment. Dipakai tombol "Preview" (link biasa target=_blank via route, BUKAN
     * aksi Livewire — Livewire selalu memaksa download apa pun response-nya). Pola persis
     * PayrollService::preview().
     */
    public function preview(EmployeeSp $sp)
    {
        $sp = $this->loadForPdf($sp);
        $pdf = Pdf::loadView('pdf.sp.sp', ['sp' => $sp, 'verification' => $this->verificationBySignerRole($sp)]);

        return $pdf->stream($this->filename($sp));
    }

    private function loadForPdf(EmployeeSp $sp): EmployeeSp
    {
        $sp->loadMissing([
            'company',
            'items',
            'user.activeJobPosition.jobPosition',
            'createdBy.activeJobPosition.jobPosition',
            'knowsBy.activeJobPosition.jobPosition',
            'coaching.coachBy.activeJobPosition.jobPosition',
            'document.signatures.verification',
        ]);

        return $sp;
    }

    /** QR per PERAN penanda tangan ('created_by'/'knows_by'), BUKAN satu QR global untuk semua
     * kolom tanda tangan (bug ditemukan user 2026-09-28: QR sempat muncul di kolom Mengetahui
     * padahal knows_by belum menandatangani, dan di kolom Karyawan yang memang tidak pernah
     * TTD digital sama sekali) - QR cuma boleh tampil di kolom peran yang BENAR-BENAR sudah ada
     * baris document_signatures-nya. Kolom yang belum ditandatangani tetap tampil ttd-space
     * kosong (tanda tangan basah/menyusul), bukan QR yang menyesatkan.
     *
     * @return array<string, ?array{code: string, qr: string}>
     */
    public function verificationBySignerRole(EmployeeSp|SpReport $signable): array
    {
        return [
            'created_by' => $this->verificationBlock($signable, 'created_by'),
            'knows_by' => $this->verificationBlock($signable, 'knows_by'),
        ];
    }

    /** Data URI SVG QR + kode verifikasi untuk 1 peran tertentu, atau null kalau peran itu belum
     *  menandatangani (kode-nya PERSONAL milik TTD peran ini sendiri - instruksi user 2026-09-29,
     *  bukan 1 kode dibagi rata ke semua peran). */
    private function verificationBlock(EmployeeSp|SpReport $signable, string $role): ?array
    {
        $document = $signable->document;

        $signature = $document?->relationLoaded('signatures')
            ? $document->signatures->firstWhere('signer_role', $role)
            : $document?->signatures()->where('signer_role', $role)->first();

        $code = $signature?->verification?->verification_code;

        if (! $code) {
            return null;
        }

        $url = route('public.verify', ['code' => $code]);
        $svg = QrCode::size(90)->generate($url);

        return [
            'code' => $code,
            'qr' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        ];
    }

    private function filename(EmployeeSp $sp): string
    {
        $level = strtoupper($sp->sp_level->value);
        $identity = $sp->user->nip ?: $sp->user_id;
        $name = str_replace(' ', '_', $sp->user->name);

        return "{$level}-{$identity}_{$name}.pdf";
    }

    public function previewBeritaAcara(EmployeeSp $sp)
    {
        $sp = $this->loadForBeritaAcaraPdf($sp);
        $verification = $sp->report ? $this->verificationBySignerRole($sp->report) : ['created_by' => null, 'knows_by' => null];
        $pdf = Pdf::loadView('pdf.sp.berita-acara', ['sp' => $sp, 'verification' => $verification]);

        return $pdf->stream($this->filename($sp));
    }

     private function loadForBeritaAcaraPdf(EmployeeSp $sp): EmployeeSp
    {
        $sp->loadMissing([
            'company',
            'report.document.signatures.verification',
            'user.profile',
            'user.activeJobPosition.jobPosition.orgStructure.parent',
            'createdBy.activeJobPosition.jobPosition',
            'knowsBy.activeJobPosition.jobPosition',
            'coaching.coachBy.activeJobPosition.jobPosition',
        ]);

        return $sp;
    }

    private function filenameBeritaAcara(EmployeeSp $sp): string
    {
        $level = strtoupper($sp->sp_level->value);
        $identity = $sp->user->nip ?: $sp->user_id;
        $name = str_replace(' ', '_', $sp->user->name);

        return "beritaacara-{$level}-{$identity}_{$name}.pdf";

    }
}
