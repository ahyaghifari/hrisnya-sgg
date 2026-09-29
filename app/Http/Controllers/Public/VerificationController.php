<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Document\DocumentVerification;
use Illuminate\View\View;

/**
 * Halaman verifikasi publik /verify/{code} (Fase 1 TTE, docs/2026-09-28-tte-qr-internal-fase-1.md
 * §5, direvisi instruksi user 2026-09-29) - tanpa login, dipagari throttle di routes/web.php.
 * 1 kode = 1 TTD PERSONAL, jadi halaman ini cuma bicara soal TTD itu sendiri (siapa yang
 * menandatangani, kapan, masih cocok datanya atau tidak) - BUKAN status keseluruhan dokumen
 * (peran lain yang mungkin belum menandatangani tidak dibocorkan di sini). Status SAH/DATA
 * BERUBAH/DICABUT DIHITUNG LIVE di sini (bukan kolom tersimpan) - lihat komentar migration
 * move_document_verification_code_to_signature.
 */
class VerificationController extends Controller
{
    public function show(string $code): View
    {
        $verification = DocumentVerification::where('verification_code', $code)
            ->with(['signature.document.documentable'])
            ->first();

        if (! $verification) {
            return view('public.verify-not-found', ['code' => $code]);
        }

        $signature = $verification->signature;
        $document = $signature->document;
        $signable = $document->documentable;

        $currentHashMatches = $signable
            ? hash('sha256', json_encode($signable->signablePayload())) === $signature->document_hash
            : false;

        return view('public.verify', [
            'document' => $document,
            'signable' => $signable,
            'signature' => $signature,
            'isRevoked' => $signable?->isRevoked() ?? false,
            'revokedReason' => $signable?->revokedReason(),
            'hashMatches' => $currentHashMatches,
            'code' => $code,
        ]);
    }
}
